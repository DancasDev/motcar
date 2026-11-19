<?php

namespace App\Libraries\Storage\Optimization;

class PdfOptimizer implements OptimizerInterface {
    private const SUPPORTED_MIMES = [
        'application/pdf',
        'application/x-pdf',
    ];

    private ?string $ghostscriptBin = null;
    private bool $checkedBin = false;

    public function supports(string $mimeType): bool {
        return in_array(strtolower(trim($mimeType)), self::SUPPORTED_MIMES, true);
    }

    public function optimize(string $sourcePath, string $mimeType): ?OptimizedFile {
        $gs = $this->resolveGhostscript();
        if ($gs === null) {
            log_message('warning', '[STORAGE_PDF_OPTIMIZE_ERROR] Ghostscript no está instalado o no se encuentra en el PATH del sistema.');
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'opt_pdf_') . '.pdf';

        try {
            $cmd = sprintf(
                '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s',
                escapeshellarg($gs),
                escapeshellarg($tempPath),
                escapeshellarg($sourcePath)
            );

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($cmd, $descriptors, $pipes);
            if (!is_resource($process)) {
                if (file_exists($tempPath)) unlink($tempPath);
                return null;
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode !== 0 || !file_exists($tempPath) || filesize($tempPath) === 0) {
                log_message('error', "[STORAGE_PDF_OPTIMIZE_ERROR] Fallo ejecucion GS (exit {$exitCode}): {$stderr} {$stdout}");
                if (file_exists($tempPath)) unlink($tempPath);
                return null;
            }

            // Validar cabecera PDF (%PDF-)
            $fh = fopen($tempPath, 'rb');
            $header = $fh ? fread($fh, 5) : '';
            if ($fh) fclose($fh);

            if (!str_starts_with($header, '%PDF-')) {
                log_message('error', '[STORAGE_PDF_OPTIMIZE_ERROR] Archivo resultante de Ghostscript no es un PDF valido.');
                if (file_exists($tempPath)) unlink($tempPath);
                return null;
            }

            return new OptimizedFile($tempPath, 'pdf', 'application/pdf');
        } catch (\Throwable $e) {
            log_message('error', '[STORAGE_PDF_OPTIMIZE_ERROR] {exception}', ['exception' => $e]);
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            return null;
        }
    }

    private function resolveGhostscript(): ?string {
        if ($this->checkedBin) {
            return $this->ghostscriptBin;
        }

        $this->checkedBin = true;

        $config = config('Storage');
        $configured = $config->ghostscriptBin ?? null;
        if (!empty($configured) && $this->isExecutable($configured)) {
            return $this->ghostscriptBin = $configured;
        }

        $candidates = [
            'gs',
            'gswin64c',
            'gswin32c',
            '/usr/bin/gs',
            '/usr/local/bin/gs',
            '/opt/homebrew/bin/gs',
        ];

        // Directorios comunes en Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $winDirs = ['C:\\Program Files\\gs', 'C:\\Program Files (x86)\\gs'];
            foreach ($winDirs as $dir) {
                if (is_dir($dir)) {
                    $found = glob($dir . '\\gs*\\bin\\gswin64c.exe');
                    if (!empty($found)) {
                        $candidates = array_merge($found, $candidates);
                    }
                }
            }
        }

        foreach ($candidates as $bin) {
            if ($this->isExecutable($bin)) {
                return $this->ghostscriptBin = $bin;
            }
        }

        return $this->ghostscriptBin = null;
    }

    private function isExecutable(string $bin): bool {
        if (file_exists($bin) && is_file($bin)) {
            return true;
        }

        $cmd = PHP_OS_FAMILY === 'Windows'
            ? 'where ' . escapeshellarg($bin) . ' 2>NUL'
            : 'which ' . escapeshellarg($bin) . ' 2>/dev/null';

        $output = @shell_exec($cmd);
        return !empty($output);
    }
}
