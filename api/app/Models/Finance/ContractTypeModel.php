<?php

namespace App\Models\Finance;

use App\Models\BaseModel;

class ContractTypeModel extends BaseModel {
    protected $table         = 'fnc_contract_type';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'code',
        'name',
        'scope',
        'metadata_schema_id',
        'is_disabled',
    ];

    protected $validationRules = [
        'code'               => 'required|alpha_dash|max_length[40]|is_unique[fnc_contract_type.code,id,{id}]',
        'name'               => 'required|string|max_length[80]',
        'scope'              => 'permit_empty|in_list[0,1]',
        'metadata_schema_id' => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[glb_metadata_schema.id,is_disabled:0;deleted_at:IS_NULL]',
        'is_disabled'        => 'permit_empty|in_list[0,1]',
    ];

    private const CONTRACT_TYPE_LIST_CACHE_KEY = 'db_fnc_ctype_list';
    private const CONTRACT_TYPE_LIST_CACHE_TTL = 3600;

    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        if ($reset) {
            $cache->delete(self::CONTRACT_TYPE_LIST_CACHE_KEY);
        }

        $data = $cache->get(self::CONTRACT_TYPE_LIST_CACHE_KEY);
        if (!is_array($data)) {
            $records = $this->select('id, code, name, scope, metadata_schema_id')
                ->where('deleted_at IS NULL')
                ->where('is_disabled', '0')
                ->findAll();

            $data = [];
            foreach ($records as $row) {
                $data[(int) $row['id']] = [
                    'code'               => $row['code'],
                    'name'               => $row['name'],
                    'scope'              => $row['scope'],
                    'metadata_schema_id' => $row['metadata_schema_id'] !== null ? (int) $row['metadata_schema_id'] : null,
                ];
            }

            $cache->save(self::CONTRACT_TYPE_LIST_CACHE_KEY, $data, self::CONTRACT_TYPE_LIST_CACHE_TTL);
        }

        return $data;
    }

    public function removeByCache(): void {
        service('cache')->delete(self::CONTRACT_TYPE_LIST_CACHE_KEY);
    }
}
