<?php

namespace App\Schemas\Gac;

use DancasDev\DQB\Schema;

class RoleEntitySchema extends Schema {
    protected array $tablesConfig = [
        'entity' => [
            'name' => 'gac_role_entity'
        ],
        'role' => [
            'name' => 'gac_role',
            'dependency' => 'role_id',
            'join' => ['type' => 'LEFT', 'on' => 'entity.role_id = role.id']
        ]
    ];

    protected array $fieldsConfig = [
        'id'              => [],
        'role_id'         => [],
        'role_code'       => ['table' => 'role', 'name' => 'code'],
        'role_name'       => ['table' => 'role', 'name' => 'name'],
        'entity_type'     => [],
        'entity_id'       => [],
        'scope_path'      => [],
        'priority'        => [],
        'is_disabled'     => [],
        'created_at'      => [],
        'updated_at'      => [],
        'deleted_at'      => ['access_level' => 2],
    ];

    public const FIELDS_CALCULATED = [
        'entity_name' => ['entity_type', 'entity_id'],
    ];

    public static function fieldsCalculatedCallback($records, $fieldsCalculated, $removeFieldsFunction): array {
        $entityIdsByType = [];

        foreach ($records as $item) {
            $type = $item['entity_type'] ?? null;
            $id   = $item['entity_id'] ?? null;
            if ($type !== null && $id !== null) {
                $entityIdsByType[$type][$id] = $id;
            }
        }

        $entityData = [];
        $conn = db_connect();

        if (!empty($entityIdsByType['1'])) {
            $result = $conn->table('gac_user')
                ->select("id, CONCAT(first_name, ' ', last_name) AS name")
                ->whereIn('id', $entityIdsByType['1'])
                ->get()
                ->getResultArray();
            foreach ($result as $row) {
                $entityData['1'][$row['id']] = $row['name'];
            }
        }

        if (!empty($entityIdsByType['2'])) {
            $result = $conn->table('gac_client')
                ->select('id, name')
                ->whereIn('id', $entityIdsByType['2'])
                ->get()
                ->getResultArray();
            foreach ($result as $row) {
                $entityData['2'][$row['id']] = $row['name'];
            }
        }

        foreach ($records as $key => $item) {
            $type = $item['entity_type'] ?? null;
            $id   = $item['entity_id'] ?? null;
            $records[$key]['entity_name'] = ($type !== null && $id !== null) ? ($entityData[$type][$id] ?? null) : null;
            $records[$key] = $removeFieldsFunction($records[$key]);
        }

        return $records;
    }
}
