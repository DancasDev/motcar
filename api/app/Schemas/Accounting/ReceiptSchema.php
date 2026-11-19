<?php

namespace App\Schemas\Accounting;

use DancasDev\DQB\Schema;

class ReceiptSchema extends Schema {
    protected array $tablesConfig = [
        'receipt' => [
            'name' => 'acc_receipt',
        ],
        'branch' => [
            'name'       => 'glb_branch',
            'dependency' => 'branch_id',
            'join'       => ['type' => 'LEFT', 'on' => 'receipt.branch_id = branch.id'],
        ],
        'client' => [
            'name'       => 'glb_person',
            'dependency' => 'client_id',
            'join'       => ['type' => 'LEFT', 'on' => 'receipt.client_id = client.id'],
        ],
        'collector' => [
            'name'       => 'gac_user',
            'dependency' => 'collector_id',
            'join'       => ['type' => 'LEFT', 'on' => 'receipt.collector_id = collector.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                          => [],
        'branch_id'                   => [],
        'branch_name'                 => ['table' => 'branch', 'name' => 'name'],
        'client_id'                   => [],
        'client_first_name'           => ['table' => 'client', 'name' => 'first_name'],
        'client_last_name'            => ['table' => 'client', 'name' => 'last_name'],
        'client_identification_type'  => ['table' => 'client', 'name' => 'identification_type'],
        'client_identification_value' => ['table' => 'client', 'name' => 'identification_value'],
        'collector_id'                => [],
        'collector_first_name'        => ['table' => 'collector', 'name' => 'first_name'],
        'collector_last_name'         => ['table' => 'collector', 'name' => 'last_name'],
        'code'                        => [],
        'date'                        => [],
        'notes'                       => [],
        'cancellation_reason'         => [],
        'status'                      => [],
        'created_at'                  => [],
        'updated_at'                  => [],
        'deleted_at'                  => ['access_level' => 2],
    ];
}
