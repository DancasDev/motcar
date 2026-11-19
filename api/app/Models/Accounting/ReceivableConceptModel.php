<?php

namespace App\Models\Accounting;

use App\Models\BaseModel;

class ReceivableConceptModel extends BaseModel {
    protected $table         = 'acc_receivable_concept';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'code',
        'name',
        'template',
        'is_disabled',
    ];

    protected $validationRules = [
        'code'        => 'required|alpha_dash|max_length[40]|is_unique[acc_receivable_concept.code,id,{id}]',
        'name'        => 'required|string|max_length[80]',
        'template'    => 'permit_empty|string|max_length[200]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    private const CONCEPT_LIST_CACHE_KEY = 'db_acc_rc_list';
    private const CONCEPT_LIST_CACHE_TTL = 3600;

    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        if ($reset) {
            $cache->delete(self::CONCEPT_LIST_CACHE_KEY);
        }

        $data = $cache->get(self::CONCEPT_LIST_CACHE_KEY);
        if (!is_array($data)) {
            $records = $this->select('id, code, name, template')
                ->where('deleted_at IS NULL')
                ->where('is_disabled', '0')
                ->findAll();

            $data = [];
            foreach ($records as $row) {
                $data[(int) $row['id']] = [
                    'code'     => $row['code'],
                    'name'     => $row['name'],
                    'template' => $row['template'],
                ];
            }

            $cache->save(self::CONCEPT_LIST_CACHE_KEY, $data, self::CONCEPT_LIST_CACHE_TTL);
        }

        return $data;
    }

    public function removeByCache(): void {
        service('cache')->delete(self::CONCEPT_LIST_CACHE_KEY);
    }
}
