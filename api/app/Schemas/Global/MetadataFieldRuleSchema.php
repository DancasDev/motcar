<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class MetadataFieldRuleSchema extends Schema {
    protected array $tablesConfig = [
        'metadata_field_rule' => [
            'name' => 'glb_metadata_field_rule',
        ],
    ];

    protected array $fieldsConfig = [
        'id'                => [],
        'code'              => [],
        'name'              => [],
        'description'       => [],
        'parameters_schema' => [],
        'rule_template'     => [],
        'is_disabled'       => [],
        'created_at'        => [],
        'updated_at'        => [],
        'deleted_at'        => ['access_level' => 2],
    ];
}
