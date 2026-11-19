<?php

namespace App\Models\Accounting;

use App\Models\BaseModel;

class PaymentAccountCategoryModel extends BaseModel {
    protected $table         = 'acc_payment_account_category';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'metadata_schema_id',
        'name',
        'is_disabled',
    ];

    protected $validationRules = [
        'metadata_schema_id' => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[glb_metadata_schema.id,is_disabled:0;deleted_at:IS_NULL]',
        'name'               => 'required|string|max_length[60]',
        'is_disabled'        => 'permit_empty|in_list[0,1]',
    ];

    private const CATEGORY_LIST_CACHE_KEY = 'db_acc_pac_list';
    private const CATEGORY_LIST_CACHE_TTL = 3600;

    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        if ($reset) {
            $cache->delete(self::CATEGORY_LIST_CACHE_KEY);
        }

        $data = $cache->get(self::CATEGORY_LIST_CACHE_KEY);
        if (!is_array($data)) {
            $records = $this->select('id, metadata_schema_id, name')
                ->where('deleted_at IS NULL')
                ->where('is_disabled', '0')
                ->findAll();

            $data = [];
            foreach ($records as $row) {
                $data[(int) $row['id']] = [
                    'name'               => $row['name'],
                    'metadata_schema_id' => $row['metadata_schema_id'] !== null ? (int) $row['metadata_schema_id'] : null,
                ];
            }

            $cache->save(self::CATEGORY_LIST_CACHE_KEY, $data, self::CATEGORY_LIST_CACHE_TTL);
        }

        return $data;
    }

    public function removeByCache(): void {
        service('cache')->delete(self::CATEGORY_LIST_CACHE_KEY);
    }
}
