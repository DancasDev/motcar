<?php

namespace App\Schemas\Finance;

use DancasDev\DQB\Schema;

class FrequencySchema extends Schema {
    protected array $tablesConfig = [
        'frequency' => [
            'name' => 'fnc_frequency',
        ],
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
