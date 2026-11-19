<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class TimeZoneSchema extends Schema {
    protected array $tablesConfig = [
        'time_zone' => [
            'name' => 'glb_time_zone'
        ]
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'code'        => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
