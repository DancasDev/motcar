<?php

namespace App\Models\Gac;

use App\Models\BaseModel;

class ModuleCategoryModel extends BaseModel {
    protected $table = 'gac_module_category';

    protected $allowedFields = [
        'code', 'name', 'description', 'scope', 'is_disabled',
    ];

    protected $validationRules = [
        'code'        => 'required|string|max_length[40]|is_unique[gac_module_category.code,id,{id}]',
        'name'        => 'required|valid_i18n[min_length:1,max_length:100]',
        'description' => 'permit_empty|valid_i18n[max_length:255]',
        'scope'       => 'permit_empty|string|regex_match[/^(?:[01](?:,[01])*)$/]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['name', 'description'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
}
