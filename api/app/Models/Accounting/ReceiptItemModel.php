<?php

namespace App\Models\Accounting;

use App\Models\BaseModel;

class ReceiptItemModel extends BaseModel {
    protected $table         = 'acc_receipt_item';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'receipt_id',
        'receivable_id',
        'amount',
        'type',
    ];

    protected $validationRules = [
        'receipt_id'    => 'required|is_natural_no_zero|is_not_unique_multi_condition[acc_receipt.id,deleted_at:IS_NULL]',
        'receivable_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[acc_receivable.id,is_disabled:0;deleted_at:IS_NULL]',
        'amount'        => 'permit_empty|decimal|greater_than[0]',
        'type'          => 'permit_empty|in_list[0,1,2,3]',
    ];
}
