<?php

namespace App\Libraries\Storage;

use Config\Storage as StorageConfig;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter as LocalVisibilityConverter;

class StorageFactory {
    public static function create(bool $isPrivate = false): Filesystem {
        $config = config('Storage');

        return match ($config->driver) {
            's3'    => self::createS3($config, $isPrivate),
            default => self::createLocal($config, $isPrivate),
        };
    }

    private static function createLocal(StorageConfig $config, bool $isPrivate): Filesystem {
        $root = $isPrivate ? $config->localPrivateRoot : $config->localPublicRoot;
        if (empty($root)) {
            $root = $isPrivate ? WRITEPATH . 'uploads/' : FCPATH . 'uploads/';
        }

        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

        $visibility = LocalVisibilityConverter::fromArray([
            'file' => ['public' => 0644, 'private' => 0600],
            'dir'  => ['public' => 0755, 'private' => 0700],
        ]);

        return new Filesystem(new LocalFilesystemAdapter($root, $visibility));
    }

    private static function createS3(StorageConfig $config, bool $isPrivate): Filesystem {
        if (empty($config->s3Key) || empty($config->s3Secret) || empty($config->s3Region) || empty($config->s3Bucket)) {
            throw new \RuntimeException('Las variables de entorno para S3 no están configuradas.');
        }

        $prefix = !empty($config->s3Prefix) ? $config->s3Prefix : 'uploads/';

        $s3Client = new \Aws\S3\S3Client([
            'credentials' => ['key' => $config->s3Key, 'secret' => $config->s3Secret],
            'region'      => $config->s3Region,
            'version'     => 'latest',
        ]);

        $visibility = new PortableVisibilityConverter();
        $options    = ['ACL' => $isPrivate ? 'private' : 'public-read'];

        return new Filesystem(
            new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                $s3Client,
                $config->s3Bucket,
                $prefix,
                $visibility,
                null,
                $options
            )
        );
    }
}
