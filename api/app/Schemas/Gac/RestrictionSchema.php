<?php

namespace App\Schemas\Gac;

use DancasDev\DQB\Schema;

class RestrictionSchema extends Schema {
    protected array $tablesConfig = [
        'restriction' => [
            'name' => 'gac_restriction'
        ]
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'entity_type' => [],
        'entity_id'   => [],
        'scope_path'  => [],
        'type'        => [],
        'rule'        => [],
        'config'      => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
