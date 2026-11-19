<?php

namespace App\Controllers\V1\Catalogs\DocumentTypes;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Catálogo global de formatos de página (solo lectura).
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\DocumentTypeModel';
    protected $schemaName = 'App\Schemas\Global\DocumentTypeSchema';

    public function index(): Response {
        return $this->baseRead();
    }

    public function show($id = null): Response {
        return $this->baseRead($id);
    }
}
