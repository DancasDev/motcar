<?php

namespace App\Schemas\Gac;

use DancasDev\DQB\Schema;

class UserSchema extends Schema {
    protected array $tablesConfig = [
        'user' => [
            'name' => 'gac_user'
        ],
        'country' => [
            'name' => 'glb_country',
            'dependency' => 'country_id',
            'join' => ['type' => 'LEFT', 'on' => 'user.country_id = country.id']
        ],
        'language' => [
            'name' => 'glb_language',
            'dependency' => 'language_id',
            'join' => ['type' => 'LEFT', 'on' => 'user.language_id = language.id']
        ],
        'time_zone' => [
            'name' => 'glb_time_zone',
            'dependency' => 'time_zone_id',
            'join' => ['type' => 'LEFT', 'on' => 'user.time_zone_id = time_zone.id']
        ],
    ];

    protected array $fieldsConfig = [
        'id'                    => [],
        'username'              => [],
        'email'                 => [],
        'email_verified_date'   => [],
        'first_name'            => [],
        'last_name'             => [],
        'sex'                   => [],
        'photo_url'             => [],
        'signature_url'         => [],
        'country_id'            => [],
        'country_code'          => ['table' => 'country', 'name' => 'code'],
        'country_name'          => ['table' => 'country', 'name' => 'name'],
        'language_id'           => [],
        'language_code'         => ['table' => 'language', 'name' => 'code'],
        'language_name'         => ['table' => 'language', 'name' => 'name'],
        'time_zone_id'          => [],
        'time_zone_code'        => ['table' => 'time_zone', 'name' => 'code'],
        'force_to'              => [],
        'notification_channels' => [],
        'telegram_chat_id'      => ['access_level' => 2],
        'telegram_username'     => [],
        'failed_attempt_count'  => ['access_level' => 2],
        'failed_attempt_date'   => ['access_level' => 2],
        'last_login_date'       => ['access_level' => 2],
        'last_login_ip'         => ['access_level' => 2],
        'metadata'              => [],
        'is_disabled'           => [],
        'created_at'            => [],
        'updated_at'            => [],
        'deleted_at'            => ['access_level' => 2]
    ];
}
