<?php

namespace App\Schemas\System;

use DancasDev\DQB\Schema;

class NotificationDeviceSchema extends Schema {
    protected array $tablesConfig = [
        'device' => [
            'name' => 'sys_notification_device',
        ],
    ];

    protected array $fieldsConfig = [
        'id'                    => [],
        'user_id'               => [],
        'device_uuid'           => [],
        'device_type'           => [],
        'device_name'           => [],
        'os_name'               => [],
        'os_version'            => [],
        'app_version'           => [],
        'push_provider'         => [],
        'push_token'            => [],
        'notifications_enabled' => [],
        'last_active_at'        => [],
        'last_ip'               => [],
        'is_disabled'           => [],
        'created_at'            => [],
        'updated_at'            => [],
        'deleted_at'            => ['access_level' => 2],
    ];
}
