<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class CountryTimeZoneModel extends BaseModel {
    protected $table = 'glb_country_time_zone';

    protected $allowedFields = [
        'country_id', 'time_zone_id', 'is_default',
    ];

    protected $validationRules = [
        'country_id'   => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_country.id,is_disabled:0;deleted_at:IS_NULL]',
        'time_zone_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_time_zone.id,is_disabled:0;deleted_at:IS_NULL]',
        'is_default'   => 'permit_empty|in_list[0,1]',
    ];
}
