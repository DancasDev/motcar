<?php

namespace App\Schemas\Gac;

use DancasDev\DQB\Schema;

class ModuleSchema extends Schema {
    protected array $tablesConfig = [
        'module' => [
            'name' => 'gac_module'
        ],
        'category' => [
            'name'       => 'gac_module_category',
            'dependency' => 'module_category_id',
            'join'       => ['type' => 'LEFT', 'on' => 'module.module_category_id = category.id']
        ]
    ];

    protected array $fieldsConfig = [
        'id'                   => [],
        'module_category_id'   => [],
        'module_category_code' => ['table' => 'category', 'name' => 'code'],
        'module_category_name' => ['table' => 'category', 'name' => 'name'],
        'code'                 => [],
        'name'                 => [],
        'description'          => [],
        'scope'                => [],
        'is_developing'        => [],
        'is_disabled'          => [],
        'created_at'           => [],
        'updated_at'           => [],
        'deleted_at'           => ['access_level' => 2],
    ];
}
