<?php

namespace App\Schemas\Accounting;

use DancasDev\DQB\Schema;

class ReceiptPaymentSchema extends Schema {
    protected array $tablesConfig = [
        'receipt_payment' => [
            'name' => 'acc_receipt_payment',
        ],
        'receipt' => [
            'name'       => 'acc_receipt',
            'dependency' => 'receipt_id',
            'join'       => ['type' => 'LEFT', 'on' => 'receipt_payment.receipt_id = receipt.id'],
        ],
        'payment_account' => [
            'name'       => 'acc_payment_account',
            'dependency' => 'origin_id',
            'join'       => ['type' => 'LEFT', 'on' => "receipt_payment.origin_type = '0' AND receipt_payment.origin_id = payment_account.id"],
        ],
        'client_credit' => [
            'name'       => 'acc_client_credit',
            'dependency' => 'origin_id',
            'join'       => ['type' => 'LEFT', 'on' => "receipt_payment.origin_type = '1' AND receipt_payment.origin_id = client_credit.id"],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                   => [],
        'receipt_id'           => [],
        'receipt_code'         => ['table' => 'receipt', 'name' => 'code'],
        'origin_type'          => [],
        'origin_id'            => [],
        'payment_account_name' => ['table' => 'payment_account', 'name' => 'name'],
        'payment_date'         => [],
        'exchange_rate'        => [],
        'amount'               => [],
        'amount_usd'           => [],
        'reference'            => [],
        'file_url'             => [],
        'notes'                => [],
        'created_at'           => [],
        'updated_at'           => [],
        'deleted_at'           => ['access_level' => 2],
    ];
}
