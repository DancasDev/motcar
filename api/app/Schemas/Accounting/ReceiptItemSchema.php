<?php

namespace App\Schemas\Accounting;

use DancasDev\DQB\Schema;

class ReceiptItemSchema extends Schema {
    protected array $tablesConfig = [
        'receipt_item' => [
            'name' => 'acc_receipt_item',
        ],
        'receipt' => [
            'name'       => 'acc_receipt',
            'dependency' => 'receipt_id',
            'join'       => ['type' => 'LEFT', 'on' => 'receipt_item.receipt_id = receipt.id'],
        ],
        'receivable' => [
            'name'       => 'acc_receivable',
            'dependency' => 'receivable_id',
            'join'       => ['type' => 'LEFT', 'on' => 'receipt_item.receivable_id = receivable.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                     => [],
        'receipt_id'             => [],
        'receipt_code'           => ['table' => 'receipt', 'name' => 'code'],
        'receivable_id'          => [],
        'receivable_description' => ['table' => 'receivable', 'name' => 'description'],
        'amount'                 => [],
        'type'                   => [],
        'created_at'             => [],
        'updated_at'             => [],
        'deleted_at'             => ['access_level' => 2],
    ];
}
