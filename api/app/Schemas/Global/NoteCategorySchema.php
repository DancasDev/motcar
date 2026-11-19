<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class NoteCategorySchema extends Schema {
    protected array $tablesConfig = [
        'note_category' => [
            'name' => 'glb_note_category',
        ],
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'name'        => [],
        'description' => [],
        'color'       => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
