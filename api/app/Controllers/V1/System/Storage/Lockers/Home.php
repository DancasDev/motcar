<?php

namespace App\Controllers\V1\System\Storage\Lockers;

use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use Config\Services;
use CodeIgniter\HTTP\Response;

/**
 * Gestión de casilleros de almacenamiento (`sys_storage_locker`) en `v1/system/storage/lockers`.
 *
 * Provisiona directorios en el backend de almacenamiento (público o privado) según `locker_key` y `is_private`.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\System\StorageLockerModel';
    protected $schemaName = 'App\Schemas\System\StorageLockerSchema';

    public function index(): Response {
        return $this->_read();
    }

    public function show($id = null): Response {
        return $this->_read($id);
    }

    public function create(): Response {
        $this->setConfig('limit', 1);
        $this->setCallback('itemSuccess', function ($id, $item) {
            $isPrivate = ($item['is_private'] ?? '0') === '1';
            try {
                Services::storage($isPrivate)->createDirectory($item['locker_key']);
            } catch (\Throwable $e) {
                log_message('error', "[STORAGE_CREATE_DIR_ERROR] Locker: {$item['locker_key']}", ['exception' => $e]);
                throw new \RuntimeException(lang('Storage.create_locker_dir_error', [$item['locker_key']]), 500, $e);
            }
        });
        return $this->baseCreate();
    }

    public function update($id = null): Response {
        $this->setConfig('limit', 1);
        $this->setCallback('setRules', function ($rules) {
            unset($rules['locker_key'], $rules['is_private']);
            return $rules;
        });
        return $this->baseUpdate($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }
        return $this->baseRead($id, $returnArray);
    }
}
