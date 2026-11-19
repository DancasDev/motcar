<?php

namespace App\Models\System;

use App\Models\BaseModel;

class NotificationModel extends BaseModel {
    // Canales
    public const CHANNEL_INAPP = '0';
    public const CHANNEL_EMAIL = '1';
    public const CHANNEL_PUSH  = '2';

    // Estados
    public const STATUS_PENDING    = '0';
    public const STATUS_SENT       = '1';
    public const STATUS_PROCESSING = '2';
    public const STATUS_READ       = '3';
    public const STATUS_DLQ        = '4';

    protected $table = 'sys_notification';

    protected $allowedFields = [
        'user_id', 'notification_type_id', 'channel_type', 'channel_key',
        'title', 'message', 'payload', 'status', 'retry_count', 'max_retries',
        'next_attempt_at', 'error_message', 'sent_at', 'read_at',
    ];

    protected $validationRules = [
        'user_id'              => 'required|is_natural_no_zero|is_not_unique_multi_condition[gac_user.id,is_disabled:0;deleted_at:IS_NULL]',
        'notification_type_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[sys_notification_type.id,is_disabled:0;deleted_at:IS_NULL]',
        'channel_type'         => 'required|in_list[0,1,2]',
        'channel_key'          => 'permit_empty|string|max_length[191]',
        'title'                => 'required|string|max_length[255]',
        'message'              => 'required|string',
        'payload'              => 'permit_empty|is_array',
        'status'               => 'permit_empty|in_list[0,1,2,3,4]',
        'retry_count'          => 'permit_empty|is_natural|less_than_equal_to[255]',
        'max_retries'          => 'permit_empty|is_natural|less_than_equal_to[255]',
        'next_attempt_at'      => 'permit_empty|valid_date',
        'error_message'        => 'permit_empty|string|max_length[500]',
        'sent_at'              => 'permit_empty|valid_date',
        'read_at'              => 'permit_empty|valid_date',
    ];

    protected array $jsonFields = ['payload'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
}
