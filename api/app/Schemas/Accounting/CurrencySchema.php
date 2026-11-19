<?php

namespace App\Schemas\Accounting;

use DancasDev\DQB\Schema;

class CurrencySchema extends Schema {
    protected array $tablesConfig = [
        'currency' => [
            'name' => 'acc_currency',
        ],
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'name'        => [],
        'code'        => [],
        'symbol'      => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
