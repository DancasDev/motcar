<?php

namespace App\Controllers\V1\System\Metadata\Fields;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Catálogo de tipos de campos dinámicos (solo lectura).
 */
class Types extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\MetadataFieldTypeModel';
    protected $schemaName = 'App\Schemas\Global\MetadataFieldTypeSchema';

    public function index(): Response {
        return $this->baseRead();
    }

    public function show($id = null): Response {
        return $this->baseRead($id);
    }
}
