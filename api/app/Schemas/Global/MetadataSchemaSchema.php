<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class MetadataSchemaSchema extends Schema {
    protected array $tablesConfig = [
        'metadata_schema' => [
            'name' => 'glb_metadata_schema',
        ],
    ];

    protected array $fieldsConfig = [
        'id'             => [],
        'code'           => [],
        'table_name'     => [],
        'storage_column' => [],
        'unique_keys'    => [],
        'is_disabled'    => [],
        'created_at'     => [],
        'updated_at'     => [],
        'deleted_at'     => ['access_level' => 2],
    ];
}
