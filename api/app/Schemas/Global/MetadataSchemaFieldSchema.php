<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class MetadataSchemaFieldSchema extends Schema {
    protected array $tablesConfig = [
        'metadata_schema_field' => [
            'name' => 'glb_metadata_schema_field',
        ],
        'metadata_field_type' => [
            'name'       => 'glb_metadata_field_type',
            'dependency' => 'metadata_field_type_id',
            'join'       => ['type' => 'INNER', 'on' => 'metadata_schema_field.metadata_field_type_id = metadata_field_type.id'],
        ],
        'metadata_schema_group' => [
            'name'       => 'glb_metadata_schema_group',
            'dependency' => 'metadata_schema_group_id',
            'join'       => ['type' => 'LEFT', 'on' => 'metadata_schema_field.metadata_schema_group_id = metadata_schema_group.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                         => [],
        'metadata_schema_id'         => [],
        'metadata_field_type_id'     => [],
        'metadata_field_type_code'   => ['table' => 'metadata_field_type', 'name' => 'code'],
        'metadata_schema_group_id'   => [],
        'metadata_schema_group_code' => ['table' => 'metadata_schema_group', 'name' => 'code'],
        'code'                       => [],
        'attribute_values'           => [],
        'rules'                      => [],
        'position'                   => [],
        'is_disabled'                => [],
        'created_at'                 => [],
        'updated_at'                 => [],
        'deleted_at'                 => ['access_level' => 2],
    ];
}
