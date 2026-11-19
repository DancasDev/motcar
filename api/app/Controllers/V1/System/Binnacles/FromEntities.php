<?php

namespace App\Controllers\V1\System\Binnacles;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;

/**
 * Catálogo de tipos de entidad emisoras de bitácora (from_entity_type).
 */
class FromEntities extends BaseController {
    protected $format = null;

    public function index(): Response {
        $data = [];
        $fromEntitiesLang = lang('Binnacle.from_entities');
        foreach ($fromEntitiesLang as $key => $lang) {
            $data[] = ['id' => (string) $key, 'description' => $lang['single']];
        }

        return $this->respond([
            'data' => $data,
            'count' => count($data),
            'limit' => count($data),
            'in_trash' => false,
        ]);
    }
}
