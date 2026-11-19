<?php

namespace App\Models\Accounting;

use App\Models\BaseModel;

class ReceiptPaymentModel extends BaseModel {
    protected $table         = 'acc_receipt_payment';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'receipt_id',
        'origin_type',
        'origin_id',
        'payment_date',
        'exchange_rate',
        'amount',
        'amount_usd',
        'reference',
        'file_url',
        'notes',
    ];

    protected $validationRules = [
        'receipt_id'    => 'required|is_natural_no_zero|is_not_unique_multi_condition[acc_receipt.id,deleted_at:IS_NULL]',
        'origin_type'   => 'permit_empty|in_list[0,1]',
        'origin_id'     => 'required|is_natural_no_zero',
        'payment_date'       => 'permit_empty|valid_date',
        'exchange_rate'      => 'permit_empty|decimal|greater_than[0]',
        'amount'             => 'required|decimal|greater_than[0]',
        'amount_usd'         => 'permit_empty|decimal|greater_than[0]',
        'reference'          => 'permit_empty|string|max_length[60]',
        'file_url'           => 'permit_empty|string|valid_url_strict|max_length[255]',
        'notes'              => 'permit_empty|string|max_length[255]',
    ];
}
