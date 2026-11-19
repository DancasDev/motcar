<?php

namespace App\Schemas\Accounting;

use DancasDev\DQB\Schema;

class ClientCreditSchema extends Schema {
    protected array $tablesConfig = [
        'client_credit' => [
            'name' => 'acc_client_credit',
        ],
        'branch' => [
            'name'       => 'glb_branch',
            'dependency' => 'branch_id',
            'join'       => ['type' => 'LEFT', 'on' => 'client_credit.branch_id = branch.id'],
        ],
        'client' => [
            'name'       => 'glb_person',
            'dependency' => 'client_id',
            'join'       => ['type' => 'LEFT', 'on' => 'client_credit.client_id = client.id'],
        ],
        'receipt' => [
            'name'       => 'acc_receipt',
            'dependency' => 'receipt_id',
            'join'       => ['type' => 'LEFT', 'on' => 'client_credit.receipt_id = receipt.id'],
        ],
        'payment_account' => [
            'name'       => 'acc_payment_account',
            'dependency' => 'payment_account_id',
            'join'       => ['type' => 'LEFT', 'on' => 'client_credit.payment_account_id = payment_account.id'],
        ],
        'currency' => [
            'name'       => 'acc_currency',
            'dependency' => 'currency_id',
            'join'       => ['type' => 'LEFT', 'on' => 'client_credit.currency_id = currency.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                   => [],
        'branch_id'            => [],
        'branch_name'          => ['table' => 'branch', 'name' => 'name'],
        'client_id'            => [],
        'client_first_name'    => ['table' => 'client', 'name' => 'first_name'],
        'client_last_name'     => ['table' => 'client', 'name' => 'last_name'],
        'receipt_id'           => [],
        'receipt_code'         => ['table' => 'receipt', 'name' => 'code'],
        'payment_account_id'   => [],
        'payment_account_name' => ['table' => 'payment_account', 'name' => 'name'],
        'currency_id'          => [],
        'currency_code'        => ['table' => 'currency', 'name' => 'code'],
        'currency_symbol'      => ['table' => 'currency', 'name' => 'symbol'],
        'amount'               => [],
        'exchange_rate'        => [],
        'available_amount'     => [],
        'status'               => [],
        'created_at'           => [],
        'updated_at'           => [],
        'deleted_at'           => ['access_level' => 2],
    ];
}
