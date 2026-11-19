<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class MetadataFieldTypeSchema extends Schema {
    protected array $tablesConfig = [
        'metadata_field_type' => [
            'name' => 'glb_metadata_field_type',
        ],
    ];

    protected array $fieldsConfig = [
        'id'               => [],
        'code'             => [],
        'name'             => [],
        'description'      => [],
        'attribute_schema' => [],
        'is_disabled'      => [],
        'created_at'       => [],
        'updated_at'       => [],
        'deleted_at'       => ['access_level' => 2],
    ];
}
