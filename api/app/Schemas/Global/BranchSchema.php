<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class BranchSchema extends Schema {
    protected array $tablesConfig = [
        'branch' => [
            'name' => 'glb_branch'
        ],
        'parent' => [
            'name'       => 'glb_branch',
            'dependency' => 'parent_id',
            'join'       => ['type' => 'LEFT', 'on' => 'branch.parent_id = parent.id']
        ],
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'parent_id'   => [],
        'parent_name' => ['table' => 'parent', 'name' => 'name'],
        'name'        => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
