<?php

namespace App\Models\Gac;

use App\Models\BaseModel;
use App\Libraries\Validators\SchemaValidator;

class PermissionModel extends BaseModel {
    protected $table = 'gac_permission';

    protected $allowedFields = [
        'module_id', 'entity_type', 'entity_id', 'scope_path',
        'feature', 'level', 'payload', 'is_disabled',
    ];

    protected $validationRules = [
        'module_id'  => 'required|is_natural_no_zero|is_not_unique_multi_condition[gac_module.id,is_disabled:0;deleted_at:IS_NULL]',
        'entity_type' => 'required|in_list[0,1,2]',
        'entity_id'   => 'required|is_natural_no_zero',
        'scope_path' => 'required|string|max_length[512]',
        'feature'    => 'required|is_natural',
        'level'      => 'permit_empty|in_list[0,1,2]',
        'payload'    => 'permit_empty|is_array',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['payload'];
    protected $beforeInsert = ['setDefaultActive', 'encodeJson'];
    protected $beforeUpdate = ['encodeJson'];

    protected function setDefaultActive(array $data) {
        $data['data']['is_disabled'] = $data['data']['is_disabled'] ?? '0';
        return $data;
    }

    private static array $schemaCache = [];

    /**
     * Valida y normaliza el payload contra el esquema del módulo asignado.
     */
    public function validatePayload(array &$item, ?int $permissionId = null): true|array {
        $moduleId = (int) ($item['module_id'] ?? 0);
        if ($moduleId <= 0 && $permissionId) {
            $moduleId = (int) ($this->db->table($this->table)
                ->select('module_id')
                ->where('id', $permissionId)
                ->limit(1)
                ->get()
                ->getRow('module_id') ?? 0);
        }

        if ($moduleId <= 0) {
            return true;
        }

        $schema = $this->getModuleSchema($moduleId);
        if ($schema === false) {
            return ['payload' => lang('SchemaValidator.payload_schema_invalid')];
        }

        $hasPayload = array_key_exists('payload', $item);
        $payload    = $item['payload'] ?? null;

        if ($schema === null || $payload === null || $payload === '') {
            if ($hasPayload) {
                $item['payload'] = null;
            }
            return true;
        }

        if (!is_array($payload)) {
            $payload = is_string($payload) ? json_decode($payload, true) : null;
            if (!is_array($payload)) {
                return ['payload' => lang('SchemaValidator.messages.object')];
            }
        }

        $result = (new SchemaValidator())->validate($payload, $schema);
        if (!$result->isValid()) {
            return $result->errors();
        }

        $item['payload'] = $result->data();
        return true;
    }

    private function getModuleSchema(int $moduleId): array|null|false {
        if (!array_key_exists($moduleId, self::$schemaCache)) {
            $raw = $this->db->table('gac_module')
                ->select('payload_schema')
                ->where('id', $moduleId)
                ->limit(1)
                ->get()
                ->getRow('payload_schema');

            if ($raw === null || $raw === '') {
                self::$schemaCache[$moduleId] = null;
            } else {
                $decoded = json_decode($raw, true);
                self::$schemaCache[$moduleId] = is_array($decoded) ? $decoded : false;
            }
        }

        return self::$schemaCache[$moduleId];
    }
}
