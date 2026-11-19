<?php

namespace App\Models\Gac;

use App\Models\BaseModel;

class RestrictionModel extends BaseModel {
    protected $table = 'gac_restriction';

    public const RULES_BY_TYPE = [
        'date'   => 'before,after,in_range,out_range,by_day',
        'ip'     => 'allow,deny',
        'domain' => 'allow,deny',
    ];

    protected $allowedFields = [
        'entity_type', 'entity_id', 'scope_path', 'type', 'rule', 'config',
        'is_disabled',
    ];

    protected $validationRules = [
        'entity_type' => 'required|in_list[0,1,2,3]',
        'entity_id'   => 'required|is_natural_no_zero',
        'scope_path'  => 'required|string|max_length[512]',
        'type'        => 'required|string|in_list[date,ip,domain]',
        'rule'        => 'required|string|max_length[30]',
        'config'      => 'required|is_array',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['config'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];
}
