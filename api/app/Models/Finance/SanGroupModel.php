<?php

namespace App\Models\Finance;

use App\Models\BaseModel;

class SanGroupModel extends BaseModel {
    protected $table         = 'fnc_san_group';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'branch_id',
        'frequency_id',
        'contract_type_id',
        'name',
        'installment_amount',
        'installment_count',
        'participant_max',
        'participant_count',
        'frequency_base_date',
        'status',
        'is_disabled',
    ];

    protected $validationRules = [
        'branch_id'           => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_branch.id,is_disabled:0;deleted_at:IS_NULL]',
        'frequency_id'        => 'required|is_natural_no_zero|is_not_unique_multi_condition[fnc_frequency.id,is_disabled:0;deleted_at:IS_NULL]',
        'contract_type_id'    => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[fnc_contract_type.id,is_disabled:0;deleted_at:IS_NULL]',
        'name'                => 'required|string|max_length[40]|is_unique[fnc_san_group.name,id,{id}]',
        'installment_amount'  => 'required|decimal|greater_than[0]',
        'installment_count'   => 'required|is_natural_no_zero|less_than_equal_to[99]',
        'participant_max'     => 'permit_empty|is_natural_no_zero|less_than_equal_to[255]',
        'participant_count'   => 'permit_empty|is_natural|less_than_equal_to[255]',
        'frequency_base_date' => 'required|valid_date',
        'status'              => 'permit_empty|in_list[0,1,2]',
        'is_disabled'         => 'permit_empty|in_list[0,1]',
    ];
}
