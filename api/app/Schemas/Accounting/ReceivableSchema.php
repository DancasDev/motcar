<?php

namespace App\Schemas\Accounting;

use DancasDev\DQB\Schema;

class ReceivableSchema extends Schema {
    protected array $tablesConfig = [
        'receivable' => [
            'name' => 'acc_receivable',
        ],
        'branch' => [
            'name'       => 'glb_branch',
            'dependency' => 'branch_id',
            'join'       => ['type' => 'LEFT', 'on' => 'receivable.branch_id = branch.id'],
        ],
        'client' => [
            'name'       => 'glb_person',
            'dependency' => 'client_id',
            'join'       => ['type' => 'LEFT', 'on' => 'receivable.client_id = client.id'],
        ],
        'concept' => [
            'name'       => 'acc_receivable_concept',
            'dependency' => 'receivable_concept_id',
            'join'       => ['type' => 'LEFT', 'on' => 'receivable.receivable_concept_id = concept.id'],
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
        'receivable_concept_id'       => [],
        'concept_code'                => ['table' => 'concept', 'name' => 'code'],
        'concept_name'                => ['table' => 'concept', 'name' => 'name'],
        'description'                 => [],
        'origin_type'                 => [],
        'origin_id'                   => [],
        'total_amount'                => [],
        'paid_amount'                 => [],
        'issue_date'                  => [],
        'due_date'                    => [],
        'status'                      => [],
        'is_disabled'                 => [],
        'created_at'                  => [],
        'updated_at'                  => [],
        'deleted_at'                  => ['access_level' => 2],
    ];
}
