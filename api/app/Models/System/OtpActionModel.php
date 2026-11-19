<?php

namespace App\Models\System;

use App\Models\BaseModel;

class OtpActionModel extends BaseModel {
    protected $table = 'sys_otp_action';

    protected $allowedFields = [
        'code', 'expiration_minute', 'max_attempt', 'resend_margin_minute', 'is_disabled',
    ];

    protected $validationRules = [
        'code'                => 'required|string|max_length[50]|is_unique[sys_otp_action.code,id,{id}]',
        'expiration_minute'   => 'permit_empty|is_natural_no_zero|less_than_equal_to[255]',
        'max_attempt'         => 'permit_empty|is_natural_no_zero|less_than_equal_to[255]',
        'resend_margin_minute'=> 'permit_empty|is_natural_no_zero|less_than_equal_to[255]',
        'is_disabled'         => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [];
}
