<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class MetadataFieldTypeModel extends BaseModel {
    protected $table = 'glb_metadata_field_type';

    protected $allowedFields = [
        'code', 'name', 'description', 'attribute_schema', 'is_disabled',
    ];

    protected $validationRules = [
        'code'             => 'required|string|max_length[40]|is_unique[glb_metadata_field_type.code,id,{id}]',
        'name'             => 'required|valid_i18n[min_length:1,max_length:100]',
        'description'      => 'permit_empty|valid_i18n[max_length:255]',
        'attribute_schema' => 'required|is_array',
        'is_disabled'      => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['name', 'description', 'attribute_schema'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];

    private const FIELD_TYPES_CACHE_KEY = 'db_mft_list';
    private const FIELD_TYPES_CACHE_TTL = 3600;

    /**
     * Tipos de campo indexados por id con attribute_schema decodificado.
     *
     * @return array<int, array{code: string, attribute_schema: array|null}>
     */
    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        $cacheKey = self::FIELD_TYPES_CACHE_KEY;

        if ($reset) {
            $cache->delete($cacheKey);
        }

        $types = $cache->get($cacheKey);
        if (!is_array($types)) {
            $rows = $this->select('id, code, attribute_schema')
                ->where('is_disabled', '0')
                ->where('deleted_at', null)
                ->findAll();

            $types = [];
            foreach ($rows as $row) {
                $schema = null;
                if (!empty($row['attribute_schema'])) {
                    $decoded = json_decode($row['attribute_schema'], true);
                    $schema = is_array($decoded) ? $decoded : null;
                }

                $types[(int) $row['id']] = [
                    'code'             => $row['code'],
                    'attribute_schema' => $schema,
                ];
            }

            $cache->save($cacheKey, $types, self::FIELD_TYPES_CACHE_TTL);
        }

        return $types;
    }

    /**
     * Invalida la caché de tipos de campos.
     */
    public function removeByCache(): void {
        service('cache')->delete(self::FIELD_TYPES_CACHE_KEY);
    }
}

