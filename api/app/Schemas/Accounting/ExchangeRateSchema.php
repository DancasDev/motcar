<?php

namespace App\Schemas\Accounting;

use DancasDev\DQB\Schema;

class ExchangeRateSchema extends Schema {
    protected array $tablesConfig = [
        'exchange_rate' => [
            'name' => 'acc_exchange_rate',
        ],
        'currency' => [
            'name'       => 'acc_currency',
            'dependency' => 'currency_id',
            'join'       => ['type' => 'LEFT', 'on' => 'exchange_rate.currency_id = currency.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'              => [],
        'currency_id'     => [],
        'currency_code'   => ['table' => 'currency', 'name' => 'code'],
        'currency_name'   => ['table' => 'currency', 'name' => 'name'],
        'currency_symbol' => ['table' => 'currency', 'name' => 'symbol'],
        'date'            => [],
        'value'           => [],
        'created_at'      => [],
        'updated_at'      => [],
        'deleted_at'      => ['access_level' => 2],
    ];
}
