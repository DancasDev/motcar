<?php

namespace App\Models\Gac;

use App\Models\BaseModel;

class UserModel extends BaseModel {
    protected $table = 'gac_user';

    protected $allowedFields = [
        'username', 'password', 'email', 'first_name', 'last_name', 'sex',
        'country_id', 'language_id', 'time_zone_id', 'force_to', 'notification_channels',
        'telegram_chat_id', 'telegram_username',
        'photo_url', 'signature_url',
        'metadata', 'is_disabled',
    ];

    protected $validationRules = [
        'username' => 'required|string|min_length[8]|max_length[30]|regex_match[/^[^@]+$/]|is_unique[gac_user.username,id,{id}]',
        'password' => ['required','string','min_length[8]','max_length[30]','regex_match[/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_+\[\]{}|;:,.<>?\-])[A-Za-z\d!@#$%^&*()_+\[\]{}|;:,.<>?\-]+$/]'],
        'email'    => 'required|string|max_length[120]|valid_email|is_unique[gac_user.email,id,{id}]',
        'first_name' => 'required|string|max_length[60]',
        'last_name'  => 'required|string|max_length[60]',
        'sex'        => 'permit_empty|in_list[0,1]',
        'country_id'  => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[glb_country.id,is_disabled:0;deleted_at:IS_NULL]',
        'language_id' => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[glb_language.id,is_disabled:0;deleted_at:IS_NULL]',
        'time_zone_id' => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[glb_time_zone.id,is_disabled:0;deleted_at:IS_NULL]',
        'photo_url'    => 'permit_empty|string|valid_url_strict|max_length[500]',
        'signature_url' => 'permit_empty|string|valid_url_strict|max_length[500]',
        'force_to' => 'permit_empty|string|regex_match[/^(?:[01](?:,[01])*)$/]',
        'notification_channels' => 'permit_empty|string|regex_match[/^(?:[12](?:,[12])*)$/]',
        'telegram_chat_id'      => 'permit_empty|string|max_length[255]',
        'telegram_username'     => 'permit_empty|string|max_length[255]',
        'metadata'     => 'permit_empty|is_array',
        'is_disabled'  => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['metadata'];
    protected $validationMessages = [];
    protected $beforeInsert = ['hashPassword', 'encodeJson'];
    protected $beforeUpdate = ['hashPassword', 'handlePasswordUpdateFlags', 'resetFailedAttempts', 'encodeJson'];

    protected function hashPassword(array $data) {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_BCRYPT, ['cost' => 4]);
        }
        return $data;
    }

    protected function handlePasswordUpdateFlags(array $data) {
        if (!isset($data['data']['password'])) {
            return $data;
        }
        $id = $data['id'][0] ?? null;
        $currentInPayload = $data['data']['force_to'] ?? null;
        $data['data']['force_to'] = $this->removeForceToFlag($id, ['0'], $currentInPayload);
        return $data;
    }

    protected function resetFailedAttempts(array $data) {
        if (isset($data['data']['password'])) {
            $data['data']['failed_attempt_count'] = 0;
            $data['data']['failed_attempt_date'] = null;
        }
        return $data;
    }

    public function removeForceToFlag(?int $id, array $toRemove, array|string|null $currentFlags = null): ?string {
        if ($currentFlags === null && $id !== null) {
            $user = $this->select('force_to')->find($id);
            $currentFlags = $user['force_to'] ?? '';
        }
        if (is_string($currentFlags)) {
            $currentFlags = ($currentFlags !== '') ? explode(',', $currentFlags) : [];
        } elseif ($currentFlags === null) {
            $currentFlags = [];
        }
        $remaining = array_diff($currentFlags, $toRemove);
        return !empty($remaining) ? implode(',', $remaining) : null;
    }

    /**
     * Agrega canales al listado actual de notification_channels.
     *
     * @param string|null $current Valor actual del campo ('' o null = ninguno).
     * @param array       $values  Canales a agregar (ej: ['1']).
     * @return string|null CSV resultante o null si queda vacío.
     */
    public function addNotificationChannels(?string $current, array $values): ?string {
        $channels = !empty($current) ? explode(',', $current) : [];
        foreach ($values as $value) {
            if (!in_array($value, $channels, true)) {
                $channels[] = $value;
            }
        }
        return $channels !== [] ? implode(',', $channels) : null;
    }

    /**
     * Quita canales del listado actual de notification_channels.
     *
     * @param string|null $current Valor actual del campo ('' o null = ninguno).
     * @param array       $values  Canales a quitar (ej: ['1']).
     * @return string|null CSV resultante o null si queda vacío.
     */
    public function removeNotificationChannels(?string $current, array $values): ?string {
        if (empty($current)) {
            return null;
        }
        $channels = array_diff(explode(',', $current), $values);
        return $channels !== [] ? implode(',', $channels) : null;
    }

    public function validateIntegrity(string $filterField, string|int $filterValue, bool $extrafields = false): bool|array {
        $filterFieldList = [
            'user_id'  => 'id',
            'username' => 'username',
            'email'    => 'email',
        ];

        $field = $filterFieldList[$filterField] ?? null;
        if (empty($field)) return false;

        $builder = $this->builder('gac_user')
            ->select('id AS user_id, failed_attempt_count, force_to, failed_attempt_date, is_disabled, deleted_at, language_id')
            ->where($field, $filterValue)
            ->limit(1);

        if ($extrafields) {
            $builder->select('username, password, email, email_verified_date, first_name, last_name, sex');
        }

        $result = $builder->get()->getResultArray()[0] ?? null;

        if (empty($result) || $result['deleted_at'] !== null || $result['is_disabled'] !== '0') {
            return false;
        }

        $result['force_to'] = ($result['force_to'] ?? '') !== '' ? explode(',', $result['force_to']) : [];
        return $result;
    }
}
