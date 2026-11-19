<?php

namespace App\Schemas\Gac;

use DancasDev\DQB\Schema;

class PermissionSchema extends Schema {
    protected array $tablesConfig = [
        'permission' => [
            'name' => 'gac_permission'
        ],
        'module' => [
            'name'       => 'gac_module',
            'dependency' => 'module_id',
            'join'       => ['type' => 'LEFT', 'on' => 'permission.module_id = module.id']
        ]
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'module_id'   => [],
        'module_code' => ['table' => 'module', 'name' => 'code'],
        'module_name' => ['table' => 'module', 'name' => 'name'],
        'entity_type' => [],
        'entity_id'   => [],
        'scope_path'  => [],
        'feature'     => [],
        'level'       => [],
        'payload'     => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
