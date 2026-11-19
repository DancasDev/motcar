<?php

namespace App\Controllers\V1\Access\Roles;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Entidades asignadas a un rol específico (solo lectura).
 *
 * Ruta base: v1/access/roles/{roleId}/entities
 *
 * - Leer: lista usuarios o clientes que tienen asignado el rol,
 *   con resolución dinámica y masiva de `entity_name` (sin N+1).
 */
class Entities extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\RoleEntityModel';
    protected $schemaName = 'App\Schemas\Gac\RoleEntitySchema';

    public function index($roleId = null) {
        return $this->_read($roleId);
    }

    public function show($roleId = null, $id = null) {
        return $this->_read($roleId, $id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($roleId = null, $id = null): Response|array {
        $this->setConfig('read:filtersDefault', [['role_id', $roleId]]);
        $this->setConfig('read:fieldsCalculated', $this->schema::FIELDS_CALCULATED);
        $this->setCallback('read:fieldsCalculated', $this->schema::fieldsCalculatedCallback(...));

        return $this->baseRead($id);
    }
}
