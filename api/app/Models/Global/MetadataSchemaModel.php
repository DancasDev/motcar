<?php

namespace App\Models\Global;

use App\Models\BaseModel;

class MetadataSchemaModel extends BaseModel {
    protected $table = 'glb_metadata_schema';

    protected $allowedFields = [
        'code', 'table_name', 'storage_column', 'unique_keys', 'is_disabled',
    ];

    protected $validationRules = [
        'code'           => 'required|alpha_dash|max_length[60]|is_unique[glb_metadata_schema.code,id,{id}]',
        'table_name'     => 'required|string|max_length[64]',
        'storage_column' => 'permit_empty|string|max_length[40]',
        'unique_keys'    => 'permit_empty|is_array',
        'is_disabled'    => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['unique_keys'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];

    private const SCHEMA_CACHE_PREFIX = 'db_msc_';
    private const SCHEMA_CACHE_TTL = 3600;

    /**
     * Obtiene el esquema estructurado con sus grupos y campos desde caché.
     * En miss o $reset = true, consulta la base de datos y almacena en caché.
     * Si no existe o está inactivo, almacena [] para evitar consultas recurrentes (anti-stampede).
     *
     * @param int $schemaId ID del esquema.
     * @param bool $reset Fuerza la recarga invalidando la caché existente.
     * @return array
     */
    public function getByCache(int $schemaId, bool $reset = false): array {
        $cache = service('cache');
        $cacheKey = self::SCHEMA_CACHE_PREFIX . $schemaId;

        if ($reset) {
            $cache->delete($cacheKey);
        }

        $schema = $cache->get($cacheKey);
        if (!is_array($schema)) {
            $row = $this->where('id', $schemaId)
                ->where('is_disabled', '0')
                ->where('deleted_at', null)
                ->first();

            if (!$row) {
                $schema = [];
            } else {
                $db = $this->db;

                // 1. Grupos (solo activos y no eliminados, indexados por código)
                $groupRows = $db->table('glb_metadata_schema_group')
                    ->select('id, code, title, description, position')
                    ->where('metadata_schema_id', $schemaId)
                    ->where('is_disabled', '0')
                    ->where('deleted_at', null)
                    ->orderBy('position', 'ASC')
                    ->get()
                    ->getResultArray();

                $groups = [];
                $groupCodeById = [];
                foreach ($groupRows as $group) {
                    $code = $group['code'];
                    $groupCodeById[$group['id']] = $code;
                    $groups[$code] = [
                        'title'       => !empty($group['title']) ? json_decode($group['title'], true) : null,
                        'description' => !empty($group['description']) ? json_decode($group['description'], true) : null,
                        'position'    => (int) $group['position'],
                    ];
                }

                // 2. Campos (no eliminados, con flag disabled, indexados por código)
                $fieldRows = $db->table('glb_metadata_schema_field f')
                    ->select('f.metadata_schema_group_id, f.code, t.code as type, f.attribute_values, f.rules, f.position, f.is_disabled')
                    ->join('glb_metadata_field_type t', 't.id = f.metadata_field_type_id', 'INNER')
                    ->where('f.metadata_schema_id', $schemaId)
                    ->where('f.deleted_at', null)
                    ->orderBy('f.position', 'ASC')
                    ->get()
                    ->getResultArray();

                $fields = [];
                foreach ($fieldRows as $field) {
                    $fields[$field['code']] = [
                        'group'            => $groupCodeById[$field['metadata_schema_group_id']] ?? null,
                        'type'             => $field['type'],
                        'attribute_values' => !empty($field['attribute_values']) ? json_decode($field['attribute_values'], true) : null,
                        'rules'            => !empty($field['rules']) ? json_decode($field['rules'], true) : null,
                        'position'         => (int) $field['position'],
                        'disabled'         => $field['is_disabled'] === '1',
                    ];
                }

                $rawKeys = $row['unique_keys'] ?? [];
                $uniqueKeys = is_string($rawKeys) ? (json_decode($rawKeys, true) ?: []) : (is_array($rawKeys) ? $rawKeys : []);

                $schema = [
                    'id'             => (int) $row['id'],
                    'code'           => $row['code'],
                    'table_name'     => $row['table_name'],
                    'storage_column' => $row['storage_column'] ?? 'metadata',
                    'unique_keys'    => $uniqueKeys,
                    'groups'         => $groups,
                    'fields'         => $fields,
                ];
            }

            $cache->save($cacheKey, $schema, self::SCHEMA_CACHE_TTL);
        }

        return $schema;
    }

    /**
     * Invalida la caché de un esquema.
     */
    public function removeByCache(int $schemaId): void {
        service('cache')->delete(self::SCHEMA_CACHE_PREFIX . $schemaId);
    }
}
