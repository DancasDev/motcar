<?php

namespace App\Models\Finance;

use App\Models\BaseModel;

class SanParticipantModel extends BaseModel {
    protected $table         = 'fnc_san_participant';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'branch_id',
        'san_group_id',
        'client_id',
        'referrer_id',
        'frequency_id',
        'position',
        'frequency_base_date',
        'installment_amount',
        'installment_count',
        'status',
        'is_overdue',
        'awarded_at',
        'benefit_metadata',
        'is_disabled',
    ];

    protected $validationRules = [
        'branch_id'           => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_branch.id,is_disabled:0;deleted_at:IS_NULL]',
        'san_group_id'        => 'required|is_natural_no_zero|is_not_unique_multi_condition[fnc_san_group.id,is_disabled:0;deleted_at:IS_NULL]',
        'client_id'           => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_person.id,is_disabled:0;deleted_at:IS_NULL]',
        'referrer_id'         => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[glb_person.id,is_disabled:0;deleted_at:IS_NULL]',
        'frequency_id'        => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[fnc_frequency.id,is_disabled:0;deleted_at:IS_NULL]',
        'position'            => 'permit_empty|is_natural_no_zero|less_than_equal_to[99]',
        'frequency_base_date' => 'permit_empty|valid_date[Y-m-d]',
        'installment_amount'  => 'permit_empty|decimal|greater_than[0]',
        'installment_count'   => 'permit_empty|is_natural_no_zero|less_than_equal_to[99]',
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
