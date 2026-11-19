<?php

namespace App\Libraries\Storage\Optimization;

interface OptimizerInterface {
    /**
     * Determina si el optimizador soporta el tipo MIME del archivo.
     */
    public function supports(string $mimeType): bool;

    /**
     * Procesa y optimiza el archivo en disco.
     * Retorna un OptimizedFile si la optimización fue exitosa, o null si falló.
     */
    public function optimize(string $sourcePath, string $mimeType): ?OptimizedFile;
}
