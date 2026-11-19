<?php

namespace App\Controllers\V1\Catalogs\Countries;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Consulta de zonas horarias de un país.
 *
 * Opera sobre glb_country_time_zone. Solo lectura — el país se recibe
 * por URL y el campo country_id junto a la tabla country quedan bloqueados
 * (el país es implícito).
 */
class TimeZones extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\CountryTimeZoneModel';
    protected $schemaName = 'App\Schemas\Global\CountryTimeZoneSchema';

    public function index($countryId = null): Response {
        return $this->_read($countryId);
    }

    public function show($countryId = null, $id = null): Response {
        return $this->_read($countryId, $id);
    }

    private function _read($countryId = null, $id = null, ?int $limit = null): Response {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', [['country_id', $countryId]]);
        $this->setCallback('read:schema', function ($schema) {
            $disabledConf = ['read_disabled' => true, 'order_disabled' => true, 'filter_disabled' => true, 'group_by_disabled' => true];
            $schema->addFieldConfig('country_id', $disabledConf);
            $schema->addTableConfig('country', $disabledConf);
        });

        return $this->baseRead($id);
    }
}
