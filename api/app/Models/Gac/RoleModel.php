<?php

namespace App\Models\Gac;

use App\Models\BaseModel;

class RoleModel extends BaseModel {
    protected $table = 'gac_role';

    protected $allowedFields = [
        'code', 'name', 'description', 'is_disabled',
    ];

    protected array $jsonFields = ['name', 'description'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];

    protected $validationRules = [
        'code'        => 'required|string|max_length[40]|is_unique[gac_role.code,id,{id}]',
        'name'        => 'required|valid_i18n[min_length:1,max_length:100]',
        'description' => 'permit_empty|valid_i18n[max_length:255]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];
}
