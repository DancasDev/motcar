<?php

namespace App\Models\System;

use App\Models\BaseModel;

class BinnacleModel extends BaseModel {
    protected $table = 'sys_binnacle';
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'from_entity_type', 'from_entity_id',
        'to_entity_type', 'to_entity_id', 'action',
        'date', 'ip', 'data', 'session_id',
    ];


    protected $beforeInsert      = ['encodeJson'];
    protected $beforeUpdate      = ['encodeJson'];
    protected $beforeInsertBatch = ['encodeJsonBatch'];
    protected $beforeUpdateBatch = ['encodeJsonBatch'];
    protected array $jsonFields  = ['data'];

    protected $validationRules = [
        'from_entity_type' => 'required|in_list[0,1,2,3]',
        'from_entity_id'   => 'permit_empty|is_natural_no_zero',
        'to_entity_type'   => 'required|string|max_length[100]|regex_match[/^[a-z0-9]+(_[a-z0-9]+)*$/]',
        'to_entity_id'     => 'permit_empty|string|max_length[100]',
        'action'           => 'required|string|max_length[50]|regex_match[/^[a-z0-9]+(_[a-z0-9]+)*$/]',
        'date'             => 'required|valid_date',
        'ip'               => 'permit_empty|string|max_length[45]',
        'data'             => 'permit_empty|is_array',
        'session_id'       => 'permit_empty|string|max_length[255]',
    ];

    protected array $fromEntityTypeKeys = [
        'system'   => '0',
        'user'     => '1',
        'client'   => '2',
        'external' => '3',
    ];

    public function log(
        int|string $from_entity_type,
        ?int $from_entity_id,
        string $to_entity_type,
        ?string $to_entity_id,
        string $action,
        ?string $date,
        string $ip,
        mixed $data,
        ?string $session_id = null
    ): int|bool {
        $from_entity_type = $this->fromEntityTypeKeys[$from_entity_type] ?? $from_entity_type;

        if (in_array($from_entity_type, ['0', '3'])) {
            $from_entity_id = null;
        }

        $data = is_array($data) ? json_encode($data) : (string) $data;

        $inserted = $this->builder()->insert([
            'from_entity_type' => (string) $from_entity_type,
            'from_entity_id'   => $from_entity_id,
            'to_entity_type'   => $to_entity_type,
            'to_entity_id'     => $to_entity_id,
            'action'           => $action,
            'date'             => $date ?? date('Y-m-d H:i:s'),
            'ip'               => $ip,
            'data'             => empty($data) ? null : $data,
            'session_id'       => $session_id,
        ]);

        return $inserted ? (int) $this->db->insertID() : false;
    }

    public function getEntityLabels(array $records): array {
        $response = [];
        $fromEntityTables = ['1' => 'gac_user', '2' => 'gac_client'];

        foreach ($records as $item) {
            if (isset($item['from_entity_type'], $item['from_entity_id'])) {
                $t = $fromEntityTables[$item['from_entity_type']] ?? null;
                if ($t) {
                    $response[$t][$item['from_entity_id']] = $item['from_entity_id'];
                }
            }

            if (isset($item['to_entity_type'], $item['to_entity_id'])) {
                $t = $item['to_entity_type'];
                $response[$t][$item['to_entity_id']] = $item['to_entity_id'];
            }
        }

        $labelConfigs = [
            'gac_client'          => ['select' => 'id, name AS label'],
            'gac_module'          => ['select' => 'id, code AS label'],
            'gac_module_category' => ['select' => 'id, code AS label'],
            'gac_role'            => ['select' => 'id, code AS label'],
            'gac_user'            => ['select' => 'id, CONCAT(first_name, " ", last_name) AS label'],
            'glb_country'         => ['select' => 'id, code AS label'],
            'glb_time_zone'       => ['select' => 'id, code AS label'],
            'glb_language'        => ['select' => 'id, code AS label'],
            'glb_branch'          => ['select' => 'id, name AS label'],
            'sys_storage_locker'  => ['select' => 'id, locker_key AS label'],
        ];

        foreach ($response as $table => $ids) {
            if (!isset($labelConfigs[$table])) continue;

            $result = $this->builder($labelConfigs[$table]['alias'] ?? $table)
                ->select($labelConfigs[$table]['select'])
                ->whereIn($labelConfigs[$table]['pk'] ?? 'id', $ids)
                ->limit(count($ids))
                ->get()
                ->getResultArray();

            foreach ($result as $record) {
                $response[$table][$record['id']] = $record['label'];
            }
        }

        return $response;
    }
}
