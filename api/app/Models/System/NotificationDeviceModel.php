<?php

namespace App\Models\System;

use App\Models\BaseModel;

class NotificationDeviceModel extends BaseModel {
    protected $table = 'sys_notification_device';

    protected $allowedFields = [
        'user_id',
        'device_uuid',
        'device_type',
        'device_name',
        'os_name',
        'os_version',
        'app_version',
        'push_provider',
        'push_token',
        'notifications_enabled',
        'last_active_at',
        'last_ip',
        'is_disabled',
    ];

    protected $validationRules = [
        'user_id'               => 'required|is_natural_no_zero|is_not_unique_multi_condition[gac_user.id,is_disabled:0;deleted_at:IS_NULL]',
        'device_uuid'           => 'required|string|max_length[100]',
        'device_type'           => 'required|in_list[web,mobile_android,mobile_ios,desktop_windows,desktop_macos,desktop_linux]',
        'device_name'           => 'required|string|max_length[100]',
        'os_name'               => 'permit_empty|string|max_length[50]',
        'os_version'            => 'permit_empty|string|max_length[30]',
        'app_version'           => 'permit_empty|string|max_length[30]',
        'push_provider'         => 'permit_empty|in_list[fcm]',
        'push_token'            => 'required|string',
        'notifications_enabled' => 'permit_empty|in_list[0,1]',
        'last_active_at'        => 'permit_empty|valid_date',
        'last_ip'               => 'permit_empty|valid_ip',
        'is_disabled'           => 'permit_empty|in_list[0,1]',
    ];

    public const MAX_DEVICES_PER_USER = 10;

    /**
     * Registra o actualiza el dispositivo push de un usuario.
     */
    public function registerOrUpdateDevice(int $userId, array $data, ?string $ip = null): array {
        $uuid = trim((string) ($data['device_uuid'] ?? ''));
        if (empty($uuid)) {
            throw new \InvalidArgumentException('device_uuid is required');
        }

        $now = date('Y-m-d H:i:s');

        // Buscar si ya existe para este usuario (incluyendo soft-deleted)
        $existing = $this->withDeleted()
            ->where('user_id', $userId)
            ->where('device_uuid', $uuid)
            ->first();

        $saveData = [
            'user_id'               => $userId,
            'device_uuid'           => $uuid,
            'device_type'           => $data['device_type'] ?? 'web',
            'device_name'           => $data['device_name'] ?? 'Dispositivo',
            'os_name'               => $data['os_name'] ?? null,
            'os_version'            => $data['os_version'] ?? null,
            'app_version'           => $data['app_version'] ?? null,
            'push_provider'         => 'fcm',
            'push_token'            => $data['push_token'] ?? '',
            'notifications_enabled' => isset($data['notifications_enabled']) ? (int) $data['notifications_enabled'] : 1,
            'last_active_at'        => $now,
            'last_ip'               => $ip,
            'is_disabled'           => '0',
            'deleted_at'            => null,
        ];

        if ($existing) {
            $this->update($existing['id'], $saveData);
            $deviceId = (int) $existing['id'];
        } else {
            // Aplicar política de límite LRU: si excede 10 dispositivos, dar de baja los más antiguos
            $this->enforceDeviceLimit($userId);
            $deviceId = (int) $this->insert($saveData, true);
        }

        return $this->find($deviceId);
    }

    /**
     * Mantiene como máximo MAX_DEVICES_PER_USER dispositivos por usuario.
     */
    public function enforceDeviceLimit(int $userId): void {
        $activeDevices = $this->where('user_id', $userId)
            ->orderBy('last_active_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        $count = count($activeDevices);
        if ($count >= self::MAX_DEVICES_PER_USER) {
            $toDelete = $count - self::MAX_DEVICES_PER_USER + 1;
            for ($i = 0; $i < $toDelete; $i++) {
                if (isset($activeDevices[$i]['id'])) {
                    $this->delete($activeDevices[$i]['id']);
                }
            }
        }
    }

    /**
     * Obtiene los tokens push activos de los dispositivos suscritos de un usuario.
     *
     * @return array<int, array{id: int, push_token: string, device_name: string}>
     */
    public function getActivePushTokens(int $userId): array {
        return $this->select('id, push_token, device_name, device_type')
            ->where('user_id', $userId)
            ->where('notifications_enabled', 1)
            ->where('is_disabled', '0')
            ->where('deleted_at', null)
            ->findAll();
    }

    /**
     * Deshabilita o elimina dispositivos cuyos tokens hayan sido invalidados por FCM (410 Gone / Unregistered).
     */
    public function deactivateInvalidToken(string $token): void {
        $this->where('push_token', $token)
            ->set(['notifications_enabled' => 0, 'is_disabled' => '1'])
            ->update();
    }
}
