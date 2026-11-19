<?php

namespace App\Models\Accounting;

use App\Models\BaseModel;

class ExchangeRateModel extends BaseModel {
    protected $table         = 'acc_exchange_rate';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'currency_id',
        'date',
        'value',
    ];

    protected $validationRules = [
        'currency_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[acc_currency.id,is_disabled:0;deleted_at:IS_NULL]',
        'date'        => 'required|valid_date',
        'value'       => 'required|decimal|greater_than[0]',
    ];

    public function getLatestRateByCurrency(int $currencyId): ?array {
        return $this->select('id, currency_id, date, value')
            ->where('currency_id', $currencyId)
            ->where('deleted_at IS NULL')
            ->orderBy('date', 'DESC')
            ->limit(1)
            ->first();
    }
}
