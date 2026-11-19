<?php

namespace App\Controllers\V1\System\Storage;

use App\Controllers\V1\BaseController;
use App\Controllers\Traits\BinnacleTraits;
use App\Libraries\Storage\Optimization\StorageOptimizer;
use Config\Services;
use CodeIgniter\HTTP\Response;

/**
 * Carga de archivos hacia lockers del sistema (POST /v1/system/storage/:lockerKey).
 *
 * Valida MIME types permitidos, tamaño máximo, optimiza imágenes si está configurado,
 * y almacena el archivo en el driver de almacenamiento activo (Local o S3).
 */
class Home extends BaseController {
    use BinnacleTraits;

    protected $modelName = 'App\Models\System\StorageLockerModel';

    public function upload(string $lockerKey = null): Response {
        $locker = $this->model
            ->select('id, locker_key, is_disabled, is_private, allowed_mimes, max_size, optimize_file')
            ->where('locker_key', $lockerKey)
            ->first();

        if (!$locker || $locker['is_disabled'] === '1') {
            return $this->fail(lang('Storage.locker_not_found', [$lockerKey]), 404, 'STORAGE_LOCKER_NOT_FOUND');
        }

        $accessError = $this->assertLockerAccess($locker);
        if ($accessError) {
            return $accessError;
        }

        $file = $this->request->getFile('file');
        if ($file === null || !$file->isValid()) {
            $msg = $file !== null ? $file->getErrorString() : lang('Storage.no_file_received');
            return $this->fail($msg, 400, 'STORAGE_UPLOAD_ERROR');
        }

        // Validación de tipos MIME permitidos
        $allowedMimes = array_filter(array_map('trim', explode(',', $locker['allowed_mimes'] ?? '')));
        if ($allowedMimes) {
            $mime = $file->getMimeType();
            $allowed = false;
            foreach ($allowedMimes as $pattern) {
                if (fnmatch($pattern, $mime)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                return $this->fail(lang('Storage.mime_type_not_allowed', [$mime]), 400, 'STORAGE_MIME_TYPE_NOT_ALLOWED');
            }
        }

        // Validación de tamaño máximo (KB)
        if (!empty($locker['max_size']) && $file->getSize() > ((int) $locker['max_size'] * 1024)) {
            return $this->fail(lang('Storage.file_too_large', [$locker['max_size']]), 400, 'STORAGE_FILE_TOO_LARGE');
        }

        $filename   = $file->getRandomName();
        $sourcePath = $file->getTempName();
        $tempFile   = null;

        // Optimización de archivo (obligatoria si el locker lo exige)
        if (($locker['optimize_file'] ?? '0') === '1') {
            $optimized = StorageOptimizer::optimize($sourcePath, $file->getMimeType());
            if ($optimized === null) {
                return $this->fail(lang('Storage.optimization_failed', [$file->getClientName()]), 422, 'STORAGE_OPTIMIZATION_FAILED');
            }

            $sourcePath = $tempFile = $optimized->path;
            $filename   = pathinfo($filename, PATHINFO_FILENAME) . '.' . $optimized->extension;
        }

        $isPrivate  = ($locker['is_private'] ?? '0') === '1';
        $filesystem = Services::storage($isPrivate);
        $path       = "{$lockerKey}/{$filename}";

        $stream = @fopen($sourcePath, 'rb');
        if (!$stream) {
            if ($tempFile && file_exists($tempFile)) unlink($tempFile);
            return $this->fail(lang('Storage.upload_error', ['File open failed']), 500, 'STORAGE_UPLOAD_ERROR');
        }

        try {
            $filesystem->writeStream($path, $stream);
        } catch (\Throwable $e) {
            log_message('error', "[STORAGE_UPLOAD_ERROR] Locker: {$lockerKey}, File: {$filename}", ['exception' => $e]);
            return $this->fail(lang('Storage.upload_error', [$e->getMessage()]), 500, 'STORAGE_UPLOAD_ERROR');
        } finally {
            if (is_resource($stream)) fclose($stream);
            if ($tempFile && file_exists($tempFile)) unlink($tempFile);
        }

        // Bitácora
        $token = service('authToken');
        $this->initBinnacle(
            'storage', 'create',
            $token->getPayloadValue('subt') ?? '',
            (int) ($token->getPayloadValue('sub') ?? 0),
            $token->getPayloadValue('jti') ?? '',
            $this->request->getIPAddress()
        );

        $responseData = ['path' => $path, 'filename' => $filename];
        $binnacleData = ['mime_type' => $file->getMimeType(), 'size' => $file->getSize()];

        if (!$isPrivate) {
            $url = $this->publicBaseUrl() . '/' . $path;
            $responseData['url'] = $binnacleData['url'] = $url;
        }

        $this->addRecord($path, $binnacleData);
        $this->flushBinnacle();

        return $this->respond($responseData, 201);
    }

    private function assertLockerAccess(array $locker): ?Response {
        $payload = service('context')->get('auth:permission')?->getPayload() ?? [];
        $lckIds  = $payload['lck_ids'] ?? null;
        if ($lckIds !== null && !in_array((int) $locker['id'], $lckIds, true)) {
            return $this->fail(lang('Storage.locker_access_denied', [$locker['locker_key']]), 403, 'STORAGE_LOCKER_ACCESS_DENIED');
        }
        return null;
    }

    private function publicBaseUrl(): string {
        $config = config('Storage');
        return $config->driver === 's3'
            ? rtrim($config->s3Url, '/') . '/' . trim($config->s3Prefix, '/')
            : base_url('uploads');
    }
}
