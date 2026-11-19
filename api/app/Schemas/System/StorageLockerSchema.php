<?php

namespace App\Schemas\System;

use DancasDev\DQB\Schema;

class StorageLockerSchema extends Schema {
    protected array $tablesConfig = [
        'locker' => [
            'name' => 'sys_storage_locker'
        ]
    ];
    protected array $fieldsConfig = [
        'id'            => [],
        'locker_key'    => [],
        'name'          => [],
        'description'   => [],
        'allowed_mimes' => [],
        'max_size'      => [],
        'optimize_file' => [],
        'is_private'    => [],
        'is_disabled'   => [],
        'created_at'    => [],
        'updated_at'    => [],
        'deleted_at'    => ['access_level' => 2],
    ];
}
