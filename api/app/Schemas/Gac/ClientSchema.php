<?php

namespace App\Schemas\Gac;

use DancasDev\DQB\Schema;

class ClientSchema extends Schema {
    protected array $tablesConfig = [
        'client' => [
            'name' => 'gac_client'
        ]
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'name'        => [],
        'description' => [],
        'client_id'   => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2]
    ];
}
