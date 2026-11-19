<?php

namespace App\Schemas\Finance;

use DancasDev\DQB\Schema;

class ContractTypeSchema extends Schema {
    protected array $tablesConfig = [
        'contract_type' => [
            'name' => 'fnc_contract_type',
        ],
        'metadata_schema' => [
            'name'       => 'glb_metadata_schema',
            'dependency' => 'metadata_schema_id',
            'join'       => ['type' => 'LEFT', 'on' => 'contract_type.metadata_schema_id = metadata_schema.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                   => [],
        'code'                 => [],
        'name'                 => [],
        'scope'                => [],
        'metadata_schema_id'   => [],
        'metadata_schema_code' => ['table' => 'metadata_schema', 'name' => 'code'],
        'is_disabled'          => [],
        'created_at'           => [],
        'updated_at'           => [],
        'deleted_at'           => ['access_level' => 2],
    ];
}
