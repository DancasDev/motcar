<?php

namespace App\Controllers\V1\System\Metadata\Fields;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Catálogo de reglas de validación de campos dinámicos (solo lectura).
 */
class Rules extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\MetadataFieldRuleModel';
    protected $schemaName = 'App\Schemas\Global\MetadataFieldRuleSchema';

    public function index(): Response {
        return $this->baseRead();
    }

    public function show($id = null): Response {
        return $this->baseRead($id);
    }
}
