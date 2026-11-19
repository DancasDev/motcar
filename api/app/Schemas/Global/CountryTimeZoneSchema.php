<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class CountryTimeZoneSchema extends Schema {
    protected array $tablesConfig = [
        'country_time_zone' => [
            'name' => 'glb_country_time_zone'
        ],
        'country' => [
            'name'       => 'glb_country',
            'dependency' => 'country_id',
            'join'       => ['type' => 'LEFT', 'on' => 'country_time_zone.country_id = country.id']
        ],
        'time_zone' => [
            'name'       => 'glb_time_zone',
            'dependency' => 'time_zone_id',
            'join'       => ['type' => 'LEFT', 'on' => 'country_time_zone.time_zone_id = time_zone.id']
        ],
    ];

    protected array $fieldsConfig = [
        'id'             => [],
        'country_id'     => [],
        'country_code'   => ['table' => 'country', 'name' => 'code'],
        'time_zone_id'   => [],
        'time_zone_code' => ['table' => 'time_zone', 'name' => 'code'],
        'is_default'     => [],
        'created_at'     => [],
        'updated_at'     => [],
        'deleted_at'     => ['access_level' => 2],
    ];
}
