<?php

namespace App\Controllers\V1\Financing\Contracts;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Catálogo de tipos de contrato (fnc_contract_type) — solo lectura.
 *
 * Definición a nivel global (v1/financing/contracts/types).
 *
 * - Leer: registros planos mediante ContractTypeSchema.
 */
class Types extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Finance\ContractTypeModel';
    protected $schemaName = 'App\Schemas\Finance\ContractTypeSchema';

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
