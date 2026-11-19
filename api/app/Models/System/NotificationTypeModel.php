<?php

namespace App\Models\System;

use App\Models\BaseModel;

class NotificationTypeModel extends BaseModel {
    protected $table = 'sys_notification_type';

    private const NOTIFICATION_TYPES_CACHE_KEY = 'db_ntp_list';
    private const NOTIFICATION_TYPES_CACHE_TTL = 3600;

    protected $allowedFields = [
        'code', 'name', 'description', 'channels', 'action_url', 'is_disabled',
    ];

    protected $validationRules = [
        'code'        => 'required|string|max_length[100]|is_unique[sys_notification_type.code,id,{id}]',
        'name'        => 'required|valid_i18n[min_length:1,max_length:100]',
        'description' => 'permit_empty|valid_i18n[max_length:255]',
        'channels'    => 'permit_empty|string|regex_match[/^(?:[012](?:,[012])*)$/]',
        'action_url'  => 'permit_empty|string|max_length[500]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    protected array $jsonFields = ['name', 'description'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];

    /**
     * Tipos de notificación indexados por código (cacheados).
     * Prefiere `service('cache')` y solo consulta la base de datos en miss.
     * Con `$reset = true` fuerza la recarga (borra la caché y la reconstruye).
     *
     * @return array<string, array{id: int, code: string, channels: string|null, action_url: string|null, is_disabled: string|int}>
     */
    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        $cacheKey = self::NOTIFICATION_TYPES_CACHE_KEY;

        if ($reset) {
            $cache->delete($cacheKey);
        }

        $types = $cache->get($cacheKey);
        if (!is_array($types)) {
            $rows = $this->select('id, code, channels, action_url, is_disabled')
                ->where('deleted_at', null)
                ->findAll();

            $types = [];
            foreach ($rows as $row) {
                $code = (string) $row['code'];
                $types[$code] = [
                    'id'          => (int) $row['id'],
                    'code'        => $code,
                    'channels'    => $row['channels'],
                    'action_url'  => $row['action_url'],
                    'is_disabled' => $row['is_disabled'],
                ];
            }

            $cache->save($cacheKey, $types, self::NOTIFICATION_TYPES_CACHE_TTL);
        }

        return $types;
    }

    /**
     * Invalida la caché de tipos de notificación.
     */
    public function removeByCache(): void {
        service('cache')->delete(self::NOTIFICATION_TYPES_CACHE_KEY);
    }
}
