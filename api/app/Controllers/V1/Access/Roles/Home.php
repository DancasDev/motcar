<?php

namespace App\Controllers\V1\Access\Roles;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Gestión de roles del sistema (`gac_role`) en `v1/access/roles`.
 *
 * Invalida la matriz de accesos GAC en caché (`purgeCache`) al actualizar, eliminar o restaurar roles.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\RoleModel';
    protected $schemaName = 'App\Schemas\Gac\RoleSchema';

    public function create(): Response {
        return $this->baseCreate();
    }

    public function index(): Response {
        return $this->_read();
    }

    public function show($id = null): Response {
        return $this->_read($id);
    }

    public function update($id = null): Response {
        $this->setCallback('after', function ($response) use ($id) {
            if (($response['affected'] ?? 0) > 0) {
                $this->purgeCache($id, $response);
            }
            return $response;
        });
        return $this->baseUpdate($id);
    }

    public function delete($id = null): Response {
        $this->setCallback('after', function ($response) use ($id) {
            if (($response['affected'] ?? 0) > 0) {
                $this->purgeCache($id, $response);
            }
            return $response;
        });
        return $this->baseDelete($id);
    }

    public function restore($id = null): Response {
        $this->setCallback('after', function ($response) use ($id) {
            if (($response['affected'] ?? 0) > 0) {
                $this->purgeCache($id, $response);
            }
            return $response;
        });
        return $this->baseRestore($id);
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

    private function purgeCache($roleId = null, array $response = []): void {
        $ids = $roleId !== null ? [(int) $roleId] : array_values(array_filter(array_column($response['data'] ?? [], 'id')));
        if (!empty($ids)) {
            service('authAccess')->purgeCacheBy('role', $ids);
        }
    }
}
