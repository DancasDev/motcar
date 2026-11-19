<?php

namespace App\Controllers\V1\System\Storage\Private;

use App\Controllers\V1\BaseController;
use Config\Services;
use CodeIgniter\HTTP\Response;

/**
 * Gestión de archivos privados y URLs prefirmadas.
 *
 * - signedUrl(): Genera URLs de descarga temporal (presigned S3 o action_token local).
 * - download(): Sirve el archivo privado mediante aceleración de servidor (Sendfile) o streaming.
 */
class Home extends BaseController {
    protected $modelName = 'App\Models\System\StorageLockerModel';

    public function signedUrl(string $lockerKey = null, string $filename = null): Response {
        if (empty($lockerKey)) {
            return $this->fail(lang('Storage.locker_key_required'), 400, 'STORAGE_LOCKER_KEY_REQUIRED');
        }

        if (empty($filename)) {
            return $this->fail(lang('Storage.filename_required'), 400, 'STORAGE_FILENAME_REQUIRED');
        }

        $locker = $this->model
            ->select('id, locker_key, is_disabled, is_private')
            ->where('locker_key', $lockerKey)
            ->first();

        if (!$locker || $locker['is_disabled'] === '1') {
            return $this->fail(lang('Storage.locker_not_found', [$lockerKey]), 404, 'STORAGE_LOCKER_NOT_FOUND');
        }

        if (($locker['is_private'] ?? '0') !== '1') {
            return $this->fail(lang('Storage.locker_not_private'), 400, 'STORAGE_LOCKER_NOT_PRIVATE');
        }

        $payload = service('context')->get('auth:permission')?->getPayload() ?? [];
        $lckIds  = $payload['lck_ids'] ?? null;
        if ($lckIds !== null && !in_array((int) $locker['id'], $lckIds, true)) {
            return $this->fail(lang('Storage.locker_access_denied', [$lockerKey]), 403, 'STORAGE_LOCKER_ACCESS_DENIED');
        }

        $path   = "{$lockerKey}/{$filename}";
        $config = config('Storage');
        $ttl    = config('Jwt')->getActionTtl('storage_download');

        return $config->driver === 's3'
            ? $this->generateS3PresignedUrl($config, $path, $ttl)
            : $this->generateLocalSignedUrl($path, $filename, $ttl);
    }

    public function download(): Response {
        $config = config('Storage');
        if ($config->driver === 's3') {
            return $this->fail(lang('Storage.download_not_available_s3'), 400, 'STORAGE_DOWNLOAD_NOT_AVAILABLE_S3');
        }

        $payload = service('context')->get('actionToken:payload');
        $path    = $payload['sub'] ?? null;

        if (empty($path)) {
            return $this->fail(lang('ActionToken.invalid'), 401, 'ACTION_TOKEN_INVALID');
        }

        $filesystem = Services::storage(true);
        if (!$filesystem->fileExists($path)) {
            return $this->fail(lang('Storage.file_not_found', [$path]), 404, 'STORAGE_FILE_NOT_FOUND');
        }

        $mimeType = $filesystem->mimeType($path);
        $filename = basename($path);

        // Aceleración mediante Servidor Web / FrankenPHP / Nginx
        if (!empty($config->sendfileHeader)) {
            $root     = $config->localPrivateRoot ?: WRITEPATH . 'uploads/';
            $filePath = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

            return $this->response
                ->setHeader($config->sendfileHeader, $filePath)
                ->setHeader('Content-Type', $mimeType)
                ->setHeader('Content-Disposition', "inline; filename=\"{$filename}\"")
                ->setBody('');
        }

        // Entrega estándar por streaming PHP
        $stream = $filesystem->readStream($path);
        $body   = stream_get_contents($stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Length', (string) $filesystem->fileSize($path))
            ->setHeader('Content-Disposition', "inline; filename=\"{$filename}\"")
            ->setBody($body);
    }

    private function generateS3PresignedUrl($config, string $path, int $ttl): Response {
        if (empty($config->s3Key) || empty($config->s3Secret) || empty($config->s3Region) || empty($config->s3Bucket)) {
            return $this->fail(lang('Storage.signed_url_error'), 500, 'STORAGE_S3_CONFIG_ERROR');
        }

        $s3Client = new \Aws\S3\S3Client([
            'credentials' => ['key' => $config->s3Key, 'secret' => $config->s3Secret],
            'region'      => $config->s3Region,
            'version'     => 'latest',
        ]);

        $prefix = trim($config->s3Prefix ?: 'uploads/', '/');
        $cmd    = $s3Client->getCommand('GetObject', [
            'Bucket' => $config->s3Bucket,
            'Key'    => "{$prefix}/{$path}",
        ]);

        $request = $s3Client->createPresignedRequest($cmd, "+{$ttl} seconds");

        return $this->respond([
            'signed_url' => (string) $request->getUri(),
            'expires_at' => time() + $ttl,
        ]);
    }

    private function generateLocalSignedUrl(string $path, string $filename, int $ttl): Response {
        $filesystem = Services::storage(true);
        if (!$filesystem->fileExists($path)) {
            return $this->fail(lang('Storage.file_not_found', [$filename]), 404, 'STORAGE_FILE_NOT_FOUND');
        }

        $tokenService = service('actionToken', false);
        $tokenService->setExpirationTime($ttl);
        $tokenService->setPayloadValue([
            'sub' => $path,
            'act' => 'storage_download',
        ]);

        return $this->respond([
            'signed_url' => base_url("v1/system/storage/download?token={$tokenService->encode()}"),
            'expires_at' => time() + $ttl,
        ]);
    }
}
