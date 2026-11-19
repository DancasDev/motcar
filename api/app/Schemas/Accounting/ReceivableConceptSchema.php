<?php

namespace App\Schemas\Accounting;

use DancasDev\DQB\Schema;

class ReceivableConceptSchema extends Schema {
    protected array $tablesConfig = [
        'receivable_concept' => [
            'name' => 'acc_receivable_concept',
        ],
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'code'        => [],
        'name'        => [],
        'template'    => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
