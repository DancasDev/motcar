<?php

namespace App\Controllers\V1\Catalogs\TimeZones;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Catálogo global de zonas horarias IANA (glb_time_zone) — solo lectura.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\TimeZoneModel';
    protected $schemaName = 'App\Schemas\Global\TimeZoneSchema';

    public function index(): Response {
        return $this->baseRead();
    }

    public function show($id = null): Response {
        return $this->baseRead($id);
    }
}
