<?php

namespace App\Models\Accounting;

use App\Models\BaseModel;

class ReceivableModel extends BaseModel {
    protected $table         = 'acc_receivable';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'branch_id',
        'client_id',
        'receivable_concept_id',
        'description',
        'origin_type',
        'origin_id',
        'total_amount',
        'paid_amount',
        'issue_date',
        'due_date',
        'status',
        'is_disabled',
    ];

    protected $validationRules = [
        'branch_id'             => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_branch.id,is_disabled:0;deleted_at:IS_NULL]',
        'client_id'             => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_person.id,is_disabled:0;deleted_at:IS_NULL]',
        'receivable_concept_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[acc_receivable_concept.id,is_disabled:0;deleted_at:IS_NULL]',
        'description'           => 'required|string|max_length[150]',
        'origin_type'           => 'required|in_list[0]',
        'origin_id'             => 'required|is_natural_no_zero',
        'total_amount'          => 'required|decimal|greater_than[0]',
        'paid_amount'           => 'permit_empty|decimal',
        'issue_date'            => 'required|valid_date',
        'due_date'              => 'required|valid_date',
        'status'                => 'permit_empty|in_list[0,1,2,3]',
        'is_disabled'           => 'permit_empty|in_list[0,1]',
    ];
}
