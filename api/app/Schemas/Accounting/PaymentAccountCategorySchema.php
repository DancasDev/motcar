<?php

namespace App\Schemas\Accounting;

use DancasDev\DQB\Schema;

class PaymentAccountCategorySchema extends Schema {
    protected array $tablesConfig = [
        'payment_account_category' => [
            'name' => 'acc_payment_account_category',
        ],
        'metadata_schema' => [
            'name'       => 'glb_metadata_schema',
            'dependency' => 'metadata_schema_id',
            'join'       => ['type' => 'LEFT', 'on' => 'payment_account_category.metadata_schema_id = metadata_schema.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                   => [],
        'metadata_schema_id'   => [],
        'metadata_schema_code' => ['table' => 'metadata_schema', 'name' => 'code'],
        'name'                 => [],
        'is_disabled'          => [],
        'created_at'           => [],
        'updated_at'           => [],
        'deleted_at'           => ['access_level' => 2],
    ];
}
