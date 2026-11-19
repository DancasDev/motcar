<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class CountryModel extends BaseModel {
    protected $table = 'glb_country';

    protected $allowedFields = [
        'code', 'name', 'is_disabled',
    ];

    protected $validationRules = [
        'code'        => 'required|string|max_length[2]|is_unique[glb_country.code,id,{id}]',
        'name'        => 'required|valid_i18n[min_length:1,max_length:100]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['name'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
}
