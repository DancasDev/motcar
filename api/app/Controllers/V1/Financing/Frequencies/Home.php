<?php

namespace App\Controllers\V1\Financing\Frequencies;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Catálogo de frecuencias de cobro (fnc_frequency) — solo lectura.
 *
 * Definición a nivel global (v1/financing/frequencies).
 *
 * - Leer: registros planos mediante FrequencySchema.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Finance\FrequencyModel';
    protected $schemaName = 'App\Schemas\Finance\FrequencySchema';

    public function index(): Response {
        return $this->_read();
    }

    public function show($id = null): Response {
        return $this->_read($id);
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
