<?php

namespace App\Models\Accounting;

use App\Models\BaseModel;

class PaymentAccountModel extends BaseModel {
    protected $table         = 'acc_payment_account';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'category_id',
        'currency_id',
        'name',
        'metadata',
        'is_disabled',
    ];

    protected $validationRules = [
        'category_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[acc_payment_account_category.id,is_disabled:0;deleted_at:IS_NULL]',
        'currency_id' => 'required|is_natural_no_zero|is_not_unique_multi_condition[acc_currency.id,is_disabled:0;deleted_at:IS_NULL]',
        'name'        => 'required|string|max_length[80]',
        'metadata'    => 'permit_empty|is_array',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['metadata'];
    protected $beforeInsert     = ['encodeJson'];
    protected $beforeUpdate     = ['encodeJson'];

    private const ACCOUNT_LIST_CACHE_KEY = 'db_acc_pa_list';
    private const ACCOUNT_LIST_CACHE_TTL = 3600;

    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        if ($reset) {
            $cache->delete(self::ACCOUNT_LIST_CACHE_KEY);
        }

        $data = $cache->get(self::ACCOUNT_LIST_CACHE_KEY);
        if (!is_array($data)) {
            $records = $this->select('id, category_id, currency_id, name')
                ->where('deleted_at IS NULL')
                ->where('is_disabled', '0')
                ->findAll();

            $data = [];
            foreach ($records as $row) {
                $data[(int) $row['id']] = [
                    'category_id' => (int) $row['category_id'],
                    'currency_id' => (int) $row['currency_id'],
                    'name'        => $row['name'],
                ];
            }

            $cache->save(self::ACCOUNT_LIST_CACHE_KEY, $data, self::ACCOUNT_LIST_CACHE_TTL);
        }

        return $data;
    }

    public function removeByCache(): void {
        service('cache')->delete(self::ACCOUNT_LIST_CACHE_KEY);
    }
}
