<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class TimeZoneModel extends BaseModel {
    protected $table = 'glb_time_zone';

    protected $allowedFields = [
        'code', 'is_disabled',
    ];

    protected $validationRules = [
        'code' => 'required|string|max_length[50]|is_unique[glb_time_zone.code,id,{id}]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];
}
