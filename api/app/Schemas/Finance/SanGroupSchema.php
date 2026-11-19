<?php

namespace App\Schemas\Finance;

use DancasDev\DQB\Schema;

class SanGroupSchema extends Schema {
    protected array $tablesConfig = [
        'san_group' => [
            'name' => 'fnc_san_group',
        ],
        'branch' => [
            'name'       => 'glb_branch',
            'dependency' => 'branch_id',
            'join'       => ['type' => 'LEFT', 'on' => 'san_group.branch_id = branch.id'],
        ],
        'frequency' => [
            'name'       => 'fnc_frequency',
            'dependency' => 'frequency_id',
            'join'       => ['type' => 'LEFT', 'on' => 'san_group.frequency_id = frequency.id'],
        ],
        'contract_type' => [
            'name'       => 'fnc_contract_type',
            'dependency' => 'contract_type_id',
            'join'       => ['type' => 'LEFT', 'on' => 'san_group.contract_type_id = contract_type.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                  => [],
        'branch_id'           => [],
        'branch_name'         => ['table' => 'branch', 'name' => 'name'],
        'frequency_id'        => [],
        'frequency_code'      => ['table' => 'frequency', 'name' => 'code'],
        'frequency_name'      => ['table' => 'frequency', 'name' => 'name'],
        'contract_type_id'    => [],
        'contract_type_code'  => ['table' => 'contract_type', 'name' => 'code'],
        'contract_type_name'  => ['table' => 'contract_type', 'name' => 'name'],
        'name'                => [],
        'installment_amount'  => [],
        'installment_count'   => [],
        'participant_max'     => [],
        'participant_count'   => [],
        'frequency_base_date' => [],
        'status'              => [],
        'is_disabled'         => [],
        'created_at'          => [],
        'updated_at'          => [],
        'deleted_at'          => ['access_level' => 2],
    ];
}
