<?php

namespace App\Schemas\Gac;

use DancasDev\DQB\Schema;

class RoleSchema extends Schema {
    protected array $tablesConfig = [
        'role' => [
            'name' => 'gac_role'
        ]
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'code'        => [],
        'name'        => [],
        'description' => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
