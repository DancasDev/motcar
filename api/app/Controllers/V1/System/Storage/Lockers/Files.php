<?php

namespace App\Controllers\V1\System\Storage\Lockers;

use App\Controllers\V1\BaseController;
use App\Controllers\Traits\BinnacleTraits;
use Config\Services;
use CodeIgniter\HTTP\Response;
use League\Flysystem\FilesystemException;

/**
 * Exploración y gestión de archivos contenidos dentro de un locker.
 *
 * - index(): Lista archivos del locker con tamaño, tipo MIME y última modificación.
 * - delete(): Elimina un archivo físico del locker y registra la acción en bitácora.
 */
class Files extends BaseController {
    use BinnacleTraits;

    protected $modelName = 'App\Models\System\StorageLockerModel';

    public function index(int $lockerId = null): Response {
        $locker = $this->model
            ->select('locker_key, is_disabled, is_private')
            ->find($lockerId);

        if (!$locker || $locker['is_disabled'] === '1') {
            return $this->fail(lang('Storage.locker_not_found', [$lockerId]), 404, 'STORAGE_LOCKER_NOT_FOUND');
        }

        $isPrivate  = ($locker['is_private'] ?? '0') === '1';
        $filesystem = Services::storage($isPrivate);
        $baseUrl    = !$isPrivate ? $this->publicBaseUrl() : null;
        $files      = [];

        try {
            $listing = $filesystem->listContents($locker['locker_key'], false);
            foreach ($listing as $item) {
                if (!$item->isFile() || str_starts_with(basename($item->path()), '.')) {
                    continue;
                }

                $path = $item->path();
                $fileData = [
                    'path'          => $path,
                    'size'          => $this->tryMeta(fn() => $filesystem->fileSize($path)),
                    'mime_type'     => $this->tryMeta(fn() => $filesystem->mimeType($path)),
                    'last_modified' => $this->tryMeta(fn() => $filesystem->lastModified($path)),
                ];

                if ($baseUrl !== null) {
                    $fileData['url'] = "{$baseUrl}/{$path}";
                }

                $files[] = $fileData;
            }
        } catch (FilesystemException) {}

        return $this->respond($files);
    }

    public function delete($lockerId = null, $filename = null): Response {
        if (empty($lockerId)) {
            return $this->fail(lang('Storage.locker_key_required'), 400, 'STORAGE_LOCKER_KEY_REQUIRED');
        }

        $locker = $this->model
            ->select('locker_key, is_private')
            ->find((int) $lockerId);

        if (!$locker) {
            return $this->fail(lang('Storage.locker_not_found', [$lockerId]), 404, 'STORAGE_LOCKER_NOT_FOUND');
        }

        $filename ??= $this->request->getVar('filename');
        if (empty($filename)) {
            return $this->fail(lang('Storage.filename_required'), 400, 'STORAGE_FILENAME_REQUIRED');
        }

        $isPrivate  = ($locker['is_private'] ?? '0') === '1';
        $filesystem = Services::storage($isPrivate);
        $path       = "{$locker['locker_key']}/{$filename}";

        try {
            if (!$filesystem->fileExists($path)) {
                return $this->fail(lang('Storage.file_not_found', [$filename]), 404, 'STORAGE_FILE_NOT_FOUND');
            }
            $filesystem->delete($path);
        } catch (FilesystemException $e) {
            log_message('error', "[STORAGE_DELETE_ERROR] Path: {$path}", ['exception' => $e]);
            return $this->fail(lang('Storage.delete_file_error'), 500, 'STORAGE_DELETE_ERROR');
        }

        // Bitácora
        $token = service('authToken');
        $this->initBinnacle(
            'storage', 'delete',
            $token->getPayloadValue('subt') ?? '',
            (int) ($token->getPayloadValue('sub') ?? 0),
            $token->getPayloadValue('jti') ?? '',
            $this->request->getIPAddress()
        );
        $this->addRecord($path);
        $this->flushBinnacle();

        return $this->respond(['message' => lang('Storage.file_deleted_successfully')]);
    }

    private function tryMeta(callable $fn): mixed {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }

    private function publicBaseUrl(): string {
        $config = config('Storage');
        return $config->driver === 's3'
            ? rtrim($config->s3Url, '/') . '/' . trim($config->s3Prefix, '/')
            : base_url('uploads');
    }
}
