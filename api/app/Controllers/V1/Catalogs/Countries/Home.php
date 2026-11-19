<?php

namespace App\Controllers\V1\Catalogs\Countries;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Catálogo global de países (glb_country) — solo lectura.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\CountryModel';
    protected $schemaName = 'App\Schemas\Global\CountrySchema';

    public function index(): Response {
        return $this->baseRead();
    }

    public function show($id = null): Response {
        return $this->baseRead($id);
    }
}
