<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class MetadataSchemaGroupSchema extends Schema {
    protected array $tablesConfig = [
        'metadata_schema_group' => [
            'name' => 'glb_metadata_schema_group',
        ],
    ];

    protected array $fieldsConfig = [
        'id'                 => [],
        'metadata_schema_id' => [],
        'code'               => [],
        'title'              => [],
        'description'        => [],
        'position'           => [],
        'is_disabled'        => [],
        'created_at'         => [],
        'updated_at'         => [],
        'deleted_at'         => ['access_level' => 2],
    ];
}
