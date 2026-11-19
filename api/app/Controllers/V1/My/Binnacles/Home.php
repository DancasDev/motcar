<?php

namespace App\Controllers\V1\My\Binnacles;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Consulta de la propia actividad (bitácoras) del usuario autenticado.
 *
 * Opera sobre sys_binnacle filtrado por from_entity_type='1' y
 * from_entity_id = sub del token. Solo lectura.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\System\BinnacleModel';
    protected $schemaName = 'App\Schemas\System\BinnacleSchema';

    public function index(): Response {
        return $this->_read();
    }

    public function show($id = null): Response {
        return $this->_read($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($id = null): Response {
        $userId = service('authToken')->getPayloadValue('sub');

        $this->setConfig('read:filtersDefault', [
            ['from_entity_type', '1'],
            ['from_entity_id', $userId],
        ]);
        $this->setConfig('read:order', ['id' => 'DESC']);
        $this->setConfig('read:limit', 100);
        $this->setConfig('read:groupByDisabled', true);
        $this->setConfig('read:fieldsCalculated', $this->schema::FIELDS_CALCULATED);
        $this->setCallback('read:fieldsCalculated', $this->schema::fieldsCalculatedCallback(...));

        return $this->baseRead($id);
    }
}