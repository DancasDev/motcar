<?php

namespace App\Models\System;

use App\Models\BaseModel;

class StorageLockerModel extends BaseModel {
    protected $table            = 'sys_storage_locker';
    protected $allowedFields    = [
        'locker_key',
        'name',
        'description',
        'allowed_mimes',
        'max_size',
        'optimize_file',
        'is_private',
        'is_disabled',
    ];

    protected $beforeInsert     = ['encodeJson'];
    protected $beforeUpdate     = ['protectImmutableFields', 'encodeJson'];

    /**
     * Reglas de validación:
     *
     * locker_key:
     *   - Mínimo 3, máximo 255 caracteres
     *   - Segmentos separados por "/", máximo 10 niveles
     *   - Cada segmento: solo minúsculas, números y guiones, sin guiones consecutivos
     *   - Ej: "images/profile-pics", "docs/reports/2024"
     *
     * allowed_mimes:
     *   - MIME types separados por coma, soporta comodín "*"
     *   - Ej: "image/jpeg,image/png" | "image/*" | "*" slash "*" | "image/jpeg,application/pdf"
     */
    protected $validationRules = [
        'locker_key'    => 'required|min_length[3]|max_length[255]|regex_match[/^[a-z0-9]+(-[a-z0-9]+)*(\/[a-z0-9]+(-[a-z0-9]+)*){0,9}$/]|is_unique[sys_storage_locker.locker_key,id,{id}]',
        'name'          => 'permit_empty|valid_i18n[min_length:1,max_length:100]',
        'description'   => 'permit_empty|valid_i18n[max_length:255]',
        'allowed_mimes' => 'permit_empty|regex_match[/^([a-z]+\/[a-z0-9.+*-]+|\*\/\*)(,([a-z]+\/[a-z0-9.+*-]+|\*\/\*))*$/]',
        'max_size'      => 'permit_empty|integer|is_natural',
        'optimize_file' => 'permit_empty|in_list[0,1]',
        'is_private'    => 'permit_empty|in_list[0,1]',
        'is_disabled'   => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['name', 'description'];

    protected function protectImmutableFields(array $data): array {
        unset($data['data']['is_private'], $data['data']['locker_key']);
        return $data;
    }
}
