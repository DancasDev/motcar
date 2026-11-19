<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class MetadataFieldRuleModel extends BaseModel {
    protected $table = 'glb_metadata_field_rule';

    protected $allowedFields = [
        'code', 'name', 'description', 'parameters_schema', 'rule_template', 'is_disabled',
    ];

    protected $validationRules = [
        'code'              => 'required|string|max_length[40]|is_unique[glb_metadata_field_rule.code,id,{id}]',
        'name'              => 'required|valid_i18n[min_length:1,max_length:100]',
        'description'       => 'permit_empty|valid_i18n[max_length:255]',
        'parameters_schema' => 'permit_empty|is_array',
        'rule_template'     => 'permit_empty|string|max_length[255]',
        'is_disabled'       => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['name', 'description', 'parameters_schema'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];

    private const FIELD_RULES_CACHE_KEY = 'db_mfr_list';
    private const FIELD_RULES_CACHE_TTL = 3600;

    /**
     * Reglas de campo indexadas por código con parameters_schema decodificado.
     *
     * @return array<string, array{parameters_schema: array|null, rule_template: string|null}>
     */
    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        $cacheKey = self::FIELD_RULES_CACHE_KEY;

        if ($reset) {
            $cache->delete($cacheKey);
        }

        $rules = $cache->get($cacheKey);
        if (!is_array($rules)) {
            $rows = $this->select('id, code, parameters_schema, rule_template')
                ->where('is_disabled', '0')
                ->where('deleted_at', null)
                ->findAll();

            $rules = [];
            foreach ($rows as $row) {
                $schema = null;
                if (!empty($row['parameters_schema'])) {
                    $decoded = json_decode($row['parameters_schema'], true);
                    $schema = is_array($decoded) ? $decoded : null;
                }

                $rules[$row['code']] = [
                    'parameters_schema' => $schema,
                    'rule_template'     => $row['rule_template'] ?? null,
                ];
            }

            $cache->save($cacheKey, $rules, self::FIELD_RULES_CACHE_TTL);
        }

        return $rules;
    }

    /**
     * Invalida la caché de reglas de campos.
     */
    public function removeByCache(): void {
        service('cache')->delete(self::FIELD_RULES_CACHE_KEY);
    }
}

