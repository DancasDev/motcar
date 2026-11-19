<?php

namespace App\Libraries\Comms\Outbox;

use App\Models\System\NotificationModel;
use CodeIgniter\Database\BaseConnection;

class NotificationOutbox
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Construye las filas de sys_notification para un usuario y sus canales habilitados.
     *
     * @param int          $userId
     * @param array        $renderedContent ['title' => ..., 'message' => ..., 'action_url' => ..., 'type_id' => ...]
     * @param list<string> $channels        ['0', '1', '2']
     * @param array        $channelKeys     ['email' => '...', 'push' => '...']
     * @param array        $data            Variables dinámicas originales
     *
     * @return list<array<string, mixed>>
     */
    public function buildRows(
        int $userId,
        array $renderedContent,
        array $channels,
        array $channelKeys,
        array $data = []
    ): array {
        $now = date('Y-m-d H:i:s');
        $payloadData = $data;
        if (!empty($renderedContent['action_url'])) {
            $payloadData['action_url'] = $renderedContent['action_url'];
        }
        $payload = !empty($payloadData)
            ? json_encode($payloadData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        $typeId = $renderedContent['type_id'] ?? 1;
        $maxRetries = config('Comms')->maxRetries ?? 3;
        $rows = [];

        foreach ($channels as $channelCode) {
            $isInternal = ($channelCode === NotificationModel::CHANNEL_INAPP);
            $status     = $isInternal ? NotificationModel::STATUS_SENT : NotificationModel::STATUS_PENDING;
            $sentAt     = $isInternal ? $now : null;

            $key = match ($channelCode) {
                NotificationModel::CHANNEL_EMAIL => $channelKeys['email'] ?? null,
                NotificationModel::CHANNEL_PUSH  => $channelKeys['push'] ?? null,
                default => null,
            };

            $rows[] = [
                'user_id'              => $userId,
                'notification_type_id' => $typeId,
                'channel_type'         => $channelCode,
                'channel_key'          => $key,
                'title'                => $renderedContent['title'],
                'message'              => $renderedContent['message'],
                'payload'              => $payload,
                'status'               => $status,
                'retry_count'          => 0,
                'max_retries'          => $maxRetries,
                'sent_at'              => $sentAt,
                'created_at'           => $now,
            ];
        }

        return $rows;
    }

    /**
     * Inserta un lote de filas en sys_notification de forma atómica/masiva.
     *
     * @param list<array<string, mixed>> $rows
     * @return int[] IDs de las notificaciones insertadas para canales externos
     */
    public function insertBatch(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $count = count($rows);
        $externalIndices = [];
        foreach ($rows as $index => $row) {
            if ($row['channel_type'] !== NotificationModel::CHANNEL_INAPP) {
                $externalIndices[] = $index;
            }
        }

        if ($count === 1) {
            $this->db->table('sys_notification')->insert($rows[0]);
            $insertedId = (int) $this->db->insertID();
            return !empty($externalIndices) ? [$insertedId] : [];
        }

        $this->db->table('sys_notification')->insertBatch($rows);
        $firstId = (int) $this->db->insertID();

        $externalIds = [];
        if ($firstId > 0) {
            foreach ($externalIndices as $idx) {
                $externalIds[] = $firstId + $idx;
            }
        }

        return $externalIds;
    }
}
