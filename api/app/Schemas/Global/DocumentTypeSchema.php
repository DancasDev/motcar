<?php

namespace App\Schemas\Global;

use DancasDev\DQB\Schema;

class DocumentTypeSchema extends Schema {
    protected array $tablesConfig = [
        'document_type' => [
            'name' => 'glb_document_type'
        ]
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'code'        => [],
        'name'        => [],
        'description' => [],
        'width'       => [],
        'height'      => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
