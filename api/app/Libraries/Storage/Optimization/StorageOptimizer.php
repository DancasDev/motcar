<?php

namespace App\Libraries\Storage\Optimization;

class StorageOptimizer {
    /** @var OptimizerInterface[] */
    private static ?array $optimizers = null;

    private static function init(): void {
        if (self::$optimizers === null) {
            self::$optimizers = [
                new ImageOptimizer(),
                new PdfOptimizer(),
            ];
        }
    }

    /**
     * Determina si existe algún optimizador capaz de procesar el tipo MIME dado.
     */
    public static function isSupported(string $mimeType): bool {
        self::init();

        foreach (self::$optimizers as $optimizer) {
            if ($optimizer->supports($mimeType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ejecuta la optimización del archivo según su tipo MIME.
     * Retorna un OptimizedFile si la optimización fue exitosa, o null si falló o no está soportado.
     */
    public static function optimize(string $sourcePath, string $mimeType): ?OptimizedFile {
        self::init();

        foreach (self::$optimizers as $optimizer) {
            if ($optimizer->supports($mimeType)) {
                return $optimizer->optimize($sourcePath, $mimeType);
            }
        }

        return null;
    }
}
