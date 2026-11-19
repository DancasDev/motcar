<?php

namespace App\Models\Accounting;

use App\Models\BaseModel;

class ClientCreditModel extends BaseModel {
    protected $table         = 'acc_client_credit';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'branch_id',
        'client_id',
        'receipt_id',
        'payment_account_id',
        'currency_id',
        'amount',
        'exchange_rate',
        'available_amount',
        'status',
    ];

    protected $validationRules = [
        'branch_id'          => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_branch.id,is_disabled:0;deleted_at:IS_NULL]',
        'client_id'          => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_person.id,is_disabled:0;deleted_at:IS_NULL]',
        'receipt_id'         => 'required|is_natural_no_zero|is_not_unique_multi_condition[acc_receipt.id,deleted_at:IS_NULL]',
        'payment_account_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[acc_payment_account.id,is_disabled:0;deleted_at:IS_NULL]',
        'currency_id'        => 'required|is_natural_no_zero|is_not_unique_multi_condition[acc_currency.id,is_disabled:0;deleted_at:IS_NULL]',
        'amount'             => 'required|decimal|greater_than[0]',
        'exchange_rate'      => 'required|decimal|greater_than[0]',
        'available_amount'   => 'required|decimal',
        'status'             => 'permit_empty|in_list[0,1,2]',
    ];
}
