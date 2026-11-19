<?php

namespace App\Models\Finance;

use App\Models\BaseModel;

class ContractDocumentModel extends BaseModel {
    protected $table         = 'fnc_contract_document';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'branch_id',
        'contract_type_id',
        'origin_type',
        'origin_id',
        'code',
        'issue_date',
        'signed_date',
        'file_url',
        'sign_status',
        'is_disabled',
    ];

    protected $validationRules = [
        'branch_id'        => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_branch.id,is_disabled:0;deleted_at:IS_NULL]',
        'contract_type_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[fnc_contract_type.id,is_disabled:0;deleted_at:IS_NULL]',
        'origin_type'      => 'required|in_list[0]',
        'origin_id'        => 'required|is_natural_no_zero|is_not_unique_multi_condition[fnc_san_participant.id,deleted_at:IS_NULL]',
        'code'             => 'required|string|max_length[40]|is_unique[fnc_contract_document.code,id,{id}]',
        'issue_date'       => 'required|valid_date',
        'signed_date'      => 'permit_empty|valid_date[Y-m-d]',
        'file_url'         => 'permit_empty|string|valid_url_strict|max_length[255]',
        'sign_status'      => 'permit_empty|in_list[0,1,2]',
        'is_disabled'      => 'permit_empty|in_list[0,1]',
    ];
}
