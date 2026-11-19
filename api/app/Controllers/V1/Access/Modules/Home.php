<?php

namespace App\Controllers\V1\Access\Modules;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Lectura y actualización de módulos del sistema.
 *
 * Opera sobre gac_module. No se pueden crear ni eliminar módulos
 * desde la API — solo consultar y modificar campos editables
 * (is_disabled, is_developing).
 *
 * - Actualizar: code y module_category_id bloqueados.
 * - Leer: todos los módulos con su categoría.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\ModuleModel';
    protected $schemaName = 'App\Schemas\Gac\ModuleSchema';

    public function index() {
        return $this->_read();
    }

    public function show($id = null) {
        return $this->_read($id);
    }

    public function update($id = null) {
        $this->setCallback('setRules', function ($rules) {
            unset($rules['code'], $rules['module_category_id']);
            return $rules;
        });
        $this->setCallback('after', function ($response) {
            $this->purgeCache();
            return $response;
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

    private function purgeCache() {
        service('authAccess')->purgeCache();
    }
}
