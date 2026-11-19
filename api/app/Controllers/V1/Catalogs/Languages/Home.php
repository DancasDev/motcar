<?php

namespace App\Controllers\V1\Catalogs\Languages;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Catálogo global de idiomas (glb_language) — solo lectura.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\LanguageModel';
    protected $schemaName = 'App\Schemas\Global\LanguageSchema';

    public function index(): Response {
        return $this->baseRead();
    }

    public function show($id = null): Response {
        return $this->baseRead($id);
    }
}
