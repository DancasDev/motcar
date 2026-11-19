<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class LanguageSchema extends Schema {
    protected array $tablesConfig = [
        'language' => [
            'name' => 'glb_language'
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
