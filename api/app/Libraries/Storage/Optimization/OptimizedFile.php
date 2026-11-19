<?php

namespace App\Libraries\Storage\Optimization;

class OptimizedFile {
    public function __construct(
        public readonly string $path,
        public readonly string $extension,
        public readonly string $mimeType
    ) {}
}
