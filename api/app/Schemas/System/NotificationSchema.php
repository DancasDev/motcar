<?php

namespace App\Schemas\System;

use DancasDev\DQB\Schema;

class NotificationSchema extends Schema {
    protected array $tablesConfig = [
        'notification' => [
            'name' => 'sys_notification'
        ],
        'type' => [
            'name'       => 'sys_notification_type',
            'dependency' => 'notification_type_id',
            'join'       => ['type' => 'LEFT', 'on' => 'notification.notification_type_id = type.id']
        ],
    ];

    protected array $fieldsConfig = [
        'id'                     => [],
        'user_id'                => [],
        'notification_type_id'   => [],
        'notification_type_code' => ['table' => 'type', 'name' => 'code'],
        'channel_type'           => [],
        'channel_key'            => [],
        'title'                  => [],
        'message'                => [],
        'payload'                => [],
        'status'                 => [],
        'retry_count'            => [],
        'max_retries'            => [],
        'next_attempt_at'        => [],
        'error_message'          => [],
        'sent_at'                => [],
        'read_at'                => [],
        'created_at'             => [],
        'updated_at'             => [],
        'deleted_at'             => ['access_level' => 2],
    ];
}
