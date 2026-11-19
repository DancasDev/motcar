<?php

namespace App\Schemas\System;

use DancasDev\DQB\Schema;

class NotificationTypeSchema extends Schema {
    protected array $tablesConfig = [
        'type' => [
            'name' => 'sys_notification_type'
        ],
    ];

    protected array $fieldsConfig = [
        'id'          => [],
        'code'        => [],
        'name'        => [],
        'description' => [],
        'channels'    => [],
        'action_url'  => [],
        'is_disabled' => [],
        'created_at'  => [],
        'updated_at'  => [],
        'deleted_at'  => ['access_level' => 2],
    ];
}
