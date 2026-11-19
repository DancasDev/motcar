<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class CountrySchema extends Schema {
    protected array $tablesConfig = [
        'country' => [
            'name' => 'glb_country'
        ]
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'code'        => [],
        'name'        => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
