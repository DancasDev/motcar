<?php

namespace App\Models\Gac;

use App\Models\BaseModel;
use CodeIgniter\Encryption\Encryption;

class ClientModel extends BaseModel {
    protected $table = 'gac_client';

    protected $allowedFields = [
        'name', 'description', 'client_id', 'client_secret',
        'is_disabled',
    ];

    protected $validationRules = [
        'name'          => 'required|string|max_length[60]|is_unique[gac_client.name,id,{id}]',
        'description'   => 'permit_empty|string',
        'client_id'     => 'required|string|max_length[255]|is_unique[gac_client.client_id,id,{id}]',
        'client_secret' => 'required|string|max_length[255]',
        'is_disabled'   => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [];
    protected $beforeInsert = ['hashClientSecret'];
    protected $beforeUpdate = ['hashClientSecret'];

    protected function hashClientSecret(array $data) {
        if (isset($data['data']['client_secret'])) {
            $data['data']['client_secret'] = password_hash($data['data']['client_secret'], PASSWORD_BCRYPT, ['cost' => 4]);
        }
        return $data;
    }

    public function generateCredentials(): array {
        return [
            'client_id'     => bin2hex(Encryption::createKey()),
            'client_secret' => bin2hex(Encryption::createKey()),
        ];
    }

    public function validateIntegrity(string $filterField, string|int $filterValue, bool $extrafields = false): bool|array {
        $filterFieldList = [
            'id'        => 'id',
            'client_id' => 'client_id',
        ];

        $field = $filterFieldList[$filterField] ?? null;
        if (empty($field)) return false;

        $builder = $this->builder()
            ->select('id, name, failed_attempt_count, failed_attempt_date, is_disabled, deleted_at')
            ->where($field, $filterValue)
            ->limit(1);

        if ($extrafields) {
            $builder->select('client_id, client_secret');
        }

        $result = $builder->get()->getResultArray()[0] ?? null;

        if (empty($result) || $result['deleted_at'] !== null || $result['is_disabled'] !== '0') {
            return false;
        }

        return $result;
    }
}
