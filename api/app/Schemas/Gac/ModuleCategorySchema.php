<?php

namespace App\Schemas\Gac;

use DancasDev\DQB\Schema;

class ModuleCategorySchema extends Schema {
    protected array $tablesConfig = [
        'category' => [
            'name' => 'gac_module_category'
        ]
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'code'        => [],
        'name'        => [],
        'description' => [],
        'scope'       => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
