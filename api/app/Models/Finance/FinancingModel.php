<?php

namespace App\Models\Finance;

use App\Models\BaseModel;

class FinancingModel extends BaseModel {
    protected $table         = 'fnc_financing';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'branch_id',
        'client_id',
        'contract_type_id',
        'frequency_id',
        'code',
        'total_amount',
        'initial_amount',
        'installment_count',
        'installment_amount',
        'frequency_base_date',
        'status',
        'is_overdue',
        'awarded_at',
        'benefit_metadata',
        'is_disabled',
    ];

    protected $validationRules = [
        'branch_id'           => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_branch.id,is_disabled:0;deleted_at:IS_NULL]',
        'client_id'           => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_person.id,is_disabled:0;deleted_at:IS_NULL]',
        'contract_type_id'    => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[fnc_contract_type.id,scope:1;is_disabled:0;deleted_at:IS_NULL]',
        'frequency_id'        => 'required|is_natural_no_zero|is_not_unique_multi_condition[fnc_frequency.id,is_disabled:0;deleted_at:IS_NULL]',
        'code'                => 'required|string|max_length[40]|is_unique[fnc_financing.code,id,{id}]',
        'total_amount'        => 'required|decimal|greater_than[0]|less_than_equal_to[999999999999.99]',
        'initial_amount'      => 'required|decimal|greater_than[0]|less_than_equal_to[999999999999.99]',
        'installment_count'   => 'required|is_natural_no_zero|less_than_equal_to[65535]',
        'installment_amount'  => 'permit_empty|decimal|greater_than[0]|less_than_equal_to[999999999999.99]',
        'frequency_base_date' => 'required|valid_date[Y-m-d]',
        'status'              => 'permit_empty|in_list[0,1,2,3]',
        'is_overdue'          => 'permit_empty|in_list[0,1]',
        'awarded_at'          => 'permit_empty|valid_date[Y-m-d]',
        'benefit_metadata'    => 'permit_empty|is_array',
        'is_disabled'         => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['benefit_metadata'];
    protected $beforeInsert     = ['encodeJson'];
    protected $beforeUpdate     = ['encodeJson'];
}
