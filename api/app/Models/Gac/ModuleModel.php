<?php

namespace App\Models\Gac;

use App\Models\BaseModel;

class ModuleModel extends BaseModel {
    protected $table = 'gac_module';

    protected $allowedFields = [
        'module_category_id', 'code', 'name', 'description', 'scope', 'payload_schema', 'is_developing', 'is_disabled',
    ];

    protected $validationRules = [
        'module_category_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[gac_module_category.id,is_disabled:0;deleted_at:IS_NULL]',
        'code'               => 'required|string|max_length[40]|is_unique[gac_module.code,id,{id}]',
        'name'               => 'required|valid_i18n[min_length:1,max_length:100]',
        'description'        => 'permit_empty|valid_i18n[max_length:255]',
        'scope'              => 'permit_empty|string|regex_match[/^(?:[01](?:,[01])*)$/]',
        'payload_schema'     => 'permit_empty|is_array',
        'is_developing'      => 'permit_empty|in_list[0,1]',
        'is_disabled'        => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['name', 'description', 'payload_schema'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
}
