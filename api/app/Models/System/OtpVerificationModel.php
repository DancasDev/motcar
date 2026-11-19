<?php

namespace App\Models\System;

use App\Models\BaseModel;

class OtpVerificationModel extends BaseModel {
    protected $table = 'sys_otp_verification';

    protected $allowedFields = [
        'otp_action_id', 'user_id', 'otp_hash',
        'expires_at', 'status', 'failed_attempt_count',
    ];

    protected $validationRules = [
        'otp_action_id'       => 'required|is_natural_no_zero|is_not_unique_multi_condition[sys_otp_action.id,is_disabled:0;deleted_at:IS_NULL]',
        'user_id'             => 'required|is_natural_no_zero|is_not_unique_multi_condition[gac_user.id,is_disabled:0;deleted_at:IS_NULL]',
        'otp_hash'            => 'required|string|max_length[255]',
        'expires_at'          => 'required|valid_date',
        'status'              => 'permit_empty|in_list[0,1,2,3]',
        'failed_attempt_count'=> 'permit_empty|is_natural|less_than_equal_to[255]',
    ];

    protected $validationMessages = [];
}
