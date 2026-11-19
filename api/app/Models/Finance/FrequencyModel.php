<?php

namespace App\Models\Finance;

use App\Models\BaseModel;

class FrequencyModel extends BaseModel {
    protected $table         = 'fnc_frequency';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'code',
        'name',
        'is_disabled',
    ];

    protected $validationRules = [
        'code'        => 'required|alpha_dash|max_length[40]|is_unique[fnc_frequency.code,id,{id}]',
        'name'        => 'required|valid_i18n[min_length:1,max_length:100]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['name'];
    protected $beforeInsert     = ['encodeJson'];
    protected $beforeUpdate     = ['encodeJson'];

    private const FREQUENCY_LIST_CACHE_KEY = 'db_fnc_freq_list';
    private const FREQUENCY_LIST_CACHE_TTL = 3600;

    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        if ($reset) {
            $cache->delete(self::FREQUENCY_LIST_CACHE_KEY);
        }

        $data = $cache->get(self::FREQUENCY_LIST_CACHE_KEY);
        if (!is_array($data)) {
            $records = $this->select('id, code, name')
                ->where('deleted_at IS NULL')
                ->where('is_disabled', '0')
                ->findAll();

            $data = [];
            foreach ($records as $row) {
                $data[(int) $row['id']] = [
                    'code' => $row['code'],
                    'name' => is_string($row['name']) ? json_decode($row['name'], true) : $row['name'],
                ];
            }

            $cache->save(self::FREQUENCY_LIST_CACHE_KEY, $data, self::FREQUENCY_LIST_CACHE_TTL);
        }

        return $data;
    }

    public function removeByCache(): void {
        service('cache')->delete(self::FREQUENCY_LIST_CACHE_KEY);
    }
}
