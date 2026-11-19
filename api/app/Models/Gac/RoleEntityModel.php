<?php

namespace App\Models\Gac;

use App\Models\BaseModel;

class RoleEntityModel extends BaseModel {
    protected $table = 'gac_role_entity';

    protected $allowedFields = [
        'role_id', 'entity_type', 'entity_id', 'scope_path', 'priority', 'is_disabled',
    ];

    protected $validationRules = [
        'role_id'     => 'required|is_natural_no_zero|is_not_unique_multi_condition[gac_role.id,is_disabled:0;deleted_at:IS_NULL]',
        'entity_type' => 'required|in_list[1,2]',
        'entity_id'   => 'required|is_natural_no_zero',
        'scope_path'  => 'permit_empty|string',
        'priority'    => 'permit_empty|is_natural|less_than_equal_to[127]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];
}
