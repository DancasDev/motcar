<?php

namespace App\Libraries\Storage\Optimization;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class ImageOptimizer implements OptimizerInterface {
    private const SUPPORTED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/bmp',
        'image/avif',
    ];

    public function supports(string $mimeType): bool {
        return in_array(strtolower(trim($mimeType)), self::SUPPORTED_MIMES, true);
    }

    public function optimize(string $sourcePath, string $mimeType): ?OptimizedFile {
        $tempPath = tempnam(sys_get_temp_dir(), 'opt_img_') . '.webp';

        try {
            // Seleccionar Imagick si está disponible para mayor fidelidad de remuestreo; GD como fallback universal
            $manager = extension_loaded('imagick')
                ? new ImageManager(new ImagickDriver())
                : new ImageManager(new GdDriver());

            $image = $manager->read($sourcePath);

            // 1. Auto-orientación EXIF (estilo Sharp) para fotos de smartphones
            $image->orient();

            // 2. Escalado descendente inteligente (máximo 1920px sin agrandar imágenes pequeñas)
            $image->scaleDown(width: 1920);

            // 3. Conversión a WebP con calidad 80 y descarte de metadatos pesados
            $image->toWebp(80)->save($tempPath);

            if (!file_exists($tempPath) || filesize($tempPath) === 0) {
                if (file_exists($tempPath)) unlink($tempPath);
                return null;
            }

            return new OptimizedFile($tempPath, 'webp', 'image/webp');
        } catch (\Throwable $e) {
            log_message('error', '[STORAGE_IMAGE_OPTIMIZE_ERROR] {exception}', ['exception' => $e]);
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            return null;
        }
    }
}
