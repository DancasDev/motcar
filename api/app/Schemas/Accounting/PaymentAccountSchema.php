<?php

namespace App\Schemas\Accounting;

use DancasDev\DQB\Schema;

class PaymentAccountSchema extends Schema {
    protected array $tablesConfig = [
        'payment_account' => [
            'name' => 'acc_payment_account',
        ],
        'category' => [
            'name'       => 'acc_payment_account_category',
            'dependency' => 'category_id',
            'join'       => ['type' => 'LEFT', 'on' => 'payment_account.category_id = category.id'],
        ],
        'currency' => [
            'name'       => 'acc_currency',
            'dependency' => 'currency_id',
            'join'       => ['type' => 'LEFT', 'on' => 'payment_account.currency_id = currency.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'              => [],
        'category_id'     => [],
        'category_name'   => ['table' => 'category', 'name' => 'name'],
        'currency_id'     => [],
        'currency_code'   => ['table' => 'currency', 'name' => 'code'],
        'currency_symbol' => ['table' => 'currency', 'name' => 'symbol'],
        'name'            => [],
        'metadata'        => [],
        'is_disabled'     => [],
        'created_at'      => [],
        'updated_at'      => [],
        'deleted_at'      => ['access_level' => 2],
    ];
}
