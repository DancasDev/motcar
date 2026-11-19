<?php

namespace App\Controllers\V1\Access\Modules;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Lectura y actualización de categorías de módulos.
 *
 * Opera sobre gac_module_category. No se pueden crear ni eliminar
 * categorías desde la API.
 *
 * - Actualizar: code bloqueado.
 * - Leer: todas las categorías.
 */
class Categories extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\ModuleCategoryModel';
    protected $schemaName = 'App\Schemas\Gac\ModuleCategorySchema';

    public function index() {
        return $this->_read();
    }

    public function show($id = null) {
        return $this->_read($id);
    }

    public function update($id = null) {
        $this->setCallback('setRules', function ($rules) {
            unset($rules['code']);
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
