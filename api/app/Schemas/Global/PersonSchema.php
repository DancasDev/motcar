<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class PersonSchema extends Schema {
    protected array $tablesConfig = [
        'person' => [
            'name' => 'glb_person',
        ],
    ];

    protected array $fieldsConfig = [
        'id'                   => [],
        'identification_type'  => [],
        'identification_value' => [],
        'first_name'           => [],
        'last_name'            => [],
        'phone_primary'        => [],
        'phone_secondary'      => [],
        'address'              => [],
        'city'                 => [],
        'metadata'             => [],
        'is_disabled'          => [],
        'created_at'           => [],
        'updated_at'           => [],
        'deleted_at'           => ['access_level' => 2],
    ];
}
