<?php

namespace App\Controllers\V1\Financing\Notes;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Catálogo de categorías de notas CRM / cobranza (glb_note_category).
 *
 * Definición a nivel global (v1/financing/notes/categories).
 *
 * - Crear: valida nombre único y formato de color hexadecimal.
 * - Actualizar: revalida unicidad de nombre y formato de color.
 * - Leer: registros planos mediante NoteCategorySchema.
 * - Eliminar/Restaurar: soft-delete estándar.
 */
class Categories extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\NoteCategoryModel';
    protected $schemaName = 'App\Schemas\Global\NoteCategorySchema';

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
        return $this->baseUpdate($id);
    }

    public function delete($id = null): Response {
        return $this->baseDelete($id);
    }

    public function restore($id = null): Response {
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
}
