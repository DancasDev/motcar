<?php

namespace App\Schemas\Finance;

use DancasDev\DQB\Schema;

class ContractDocumentSchema extends Schema {
    protected array $tablesConfig = [
        'contract_document' => [
            'name' => 'fnc_contract_document',
        ],
        'branch' => [
            'name'       => 'glb_branch',
            'dependency' => 'branch_id',
            'join'       => ['type' => 'LEFT', 'on' => 'contract_document.branch_id = branch.id'],
        ],
        'contract_type' => [
            'name'       => 'fnc_contract_type',
            'dependency' => 'contract_type_id',
            'join'       => ['type' => 'LEFT', 'on' => 'contract_document.contract_type_id = contract_type.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                 => [],
        'branch_id'          => [],
        'branch_name'        => ['table' => 'branch', 'name' => 'name'],
        'contract_type_id'   => [],
        'contract_type_code' => ['table' => 'contract_type', 'name' => 'code'],
        'contract_type_name' => ['table' => 'contract_type', 'name' => 'name'],
        'origin_type'        => [],
        'origin_id'          => [],
        'code'               => [],
        'issue_date'         => [],
        'signed_date'        => [],
        'file_url'           => [],
        'sign_status'        => [],
        'is_disabled'        => [],
        'created_at'         => [],
        'updated_at'         => [],
        'deleted_at'         => ['access_level' => 2],
    ];
}
