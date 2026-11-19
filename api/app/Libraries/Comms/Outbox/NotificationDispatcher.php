<?php

namespace App\Libraries\Comms\Outbox;

use App\Libraries\Comms\Drivers\EmailDriver;
use App\Libraries\Comms\Drivers\FcmPushDriver;
use App\Libraries\Comms\RateLimit\CommsRateLimiter;
use App\Models\System\NotificationDeviceModel;
use App\Models\System\NotificationModel;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Database\BaseConnection;
use Config\Comms;
use Throwable;

/**
 * Despachador central de la cola Outbox de notificaciones (`sys_notification`).
 */
class NotificationDispatcher
{
    private BaseConnection $db;
    private CacheInterface $cache;
    private CommsRateLimiter $rateLimiter;
    private EmailDriver $emailDriver;
    private FcmPushDriver $pushDriver;

    public function __construct(
        ?BaseConnection $db = null,
        ?CacheInterface $cache = null,
        ?EmailDriver $emailDriver = null,
        ?FcmPushDriver $pushDriver = null
    ) {
        $this->db          = $db ?? db_connect();
        $this->cache       = $cache ?? service('cache');
        $this->rateLimiter = new CommsRateLimiter($this->cache);
        $this->emailDriver = $emailDriver ?? new EmailDriver();
        $this->pushDriver  = $pushDriver ?? new FcmPushDriver();
    }

    /**
     * Despacha un lote de notificaciones pendientes con lock atómico en MySQL.
     *
     * @param int[]|null     $specificIds         Lista opcional de IDs a procesar inmediatamente
     * @param callable|null  $heartbeatCallback   Callback opcional para renovar el lease del slot
     * @param list<string>   $allowedChannelCodes Canales permitidos ['1', '2']
     *
     * @return array{processed: int, sent: int, deferred: int, failed: int, dlq: int}
     */
    public function dispatch(?array $specificIds = null, ?callable $heartbeatCallback = null, array $allowedChannelCodes = ['1', '2']): array
    {
        $stats = [
            'processed' => 0,
            'sent'      => 0,
            'deferred'  => 0,
            'failed'    => 0,
            'dlq'       => 0,
        ];

        $candidates = $this->candidates($specificIds, $allowedChannelCodes);
        if (empty($candidates)) {
            return $stats;
        }

        // Lock atómico: masivo si son IDs específicos, por fila si es cola general/concurrente
        $claimedRows = [];
        $statusProcessing = NotificationModel::STATUS_PROCESSING;
        $statusPending = NotificationModel::STATUS_PENDING;

        if (!empty($specificIds)) {
            $candIds = array_map(static fn($c) => (int) $c['id'], $candidates);
            $this->db->table('sys_notification')
                ->whereIn('id', $candIds)
                ->whereIn('status', [$statusPending, $statusProcessing])
                ->update(['status' => $statusProcessing, 'updated_at' => date('Y-m-d H:i:s')]);
            $claimedRows = $candidates;
        } else {
            foreach ($candidates as $candidate) {
                $id = (int) $candidate['id'];
                $this->db->query(
                    "UPDATE sys_notification SET status = '{$statusProcessing}' WHERE id = ? AND status IN ('{$statusPending}', '{$statusProcessing}')",
                    [$id]
                );
                if ($this->db->affectedRows() > 0) {
                    $claimedRows[] = $candidate;
                }
            }
        }

        if (empty($claimedRows)) {
            return $stats;
        }

        $stats['processed'] = count($claimedRows);

        // Agrupar filas por canal
        $byChannel = [];
        $pushUserIds = [];

        foreach ($claimedRows as $row) {
            $ch = (string) $row['channel_type'];
            $byChannel[$ch][] = $row;
            if ($ch === '2') {
                $pushUserIds[(int) $row['user_id']] = true;
            }
        }

        // Precarga de dispositivos push activos en 1 sola consulta (solo columnas requeridas)
        $devicesByUser = [];
        if (!empty($pushUserIds)) {
            $devices = (new NotificationDeviceModel($this->db))
                ->select('user_id, push_token')
                ->whereIn('user_id', array_keys($pushUserIds))
                ->where('notifications_enabled', 1)
                ->where('is_disabled', '0')
                ->where('deleted_at', null)
                ->findAll();

            foreach ($devices as $d) {
                $devicesByUser[(int) $d['user_id']][] = $d['push_token'];
            }
        }

        // Preparar cliente SMTP con SMTPKeepAlive para envíos masivos
        $emailClient = null;
        if (!empty($byChannel['1'])) {
            $emailClient = service('email');
            $emailClient->SMTPKeepAlive = true;
        }

        /** @var Comms $comms */
        $comms = config('Comms');

        // Procesar cada canal
        foreach ($byChannel as $channelCode => $rows) {
            if (is_callable($heartbeatCallback)) {
                $heartbeatCallback();
            }

            $channelName = match ($channelCode) {
                '1' => 'email',
                '2' => 'push',
                default => 'unknown',
            };

            // 1. Despacho Masivo / Paralelo para Canal Push (FCM HTTP v1 con curl_multi)
            if ($channelCode === '2') {
                $pushBatchItems = [];
                $rowMap = [];
                $pushSentIds = [];

                foreach ($rows as $row) {
                    $rowId = (int) $row['id'];
                    $userId = (int) $row['user_id'];
                    $userTokens = $devicesByUser[$userId] ?? [];

                    if (empty($userTokens)) {
                        $pushSentIds[] = $rowId;
                        $stats['sent']++;
                        continue;
                    }

                    if ($this->rateLimiter->isThrottled('push')) {
                        $this->defer($rowId, (int) $row['retry_count'], $comms->windowFor('push'));
                        $stats['deferred']++;
                        continue;
                    }

                    $payload = !empty($row['payload']) ? json_decode((string) $row['payload'], true) ?? [] : [];

                    foreach ($userTokens as $tIdx => $token) {
                        $batchKey = "{$rowId}_{$tIdx}";
                        $pushBatchItems[$batchKey] = [
                            'token'   => $token,
                            'title'   => (string) $row['title'],
                            'message' => (string) $row['message'],
                            'data'    => $payload,
                        ];
                        $rowMap[$batchKey] = $row;
                    }
                }

                if (!empty($pushBatchItems)) {
                    $batchResults = $this->pushDriver->sendBatch($pushBatchItems);
                    $rowSuccess = [];
                    $rowErrors = [];

                    foreach ($batchResults as $batchKey => $res) {
                        $origRow = $rowMap[$batchKey];
                        $rId = (int) $origRow['id'];

                        if (!empty($res['success'])) {
                            $rowSuccess[$rId] = true;
                        } else {
                            $rowErrors[$rId][] = $res['error'] ?? 'Fallo en FCM';
                        }
                    }

                    $uniqueProcessedRows = [];
                    foreach ($rowMap as $origRow) {
                        $rId = (int) $origRow['id'];
                        if (isset($uniqueProcessedRows[$rId])) {
                            continue;
                        }
                        $uniqueProcessedRows[$rId] = true;

                        if (!empty($rowSuccess[$rId]) || empty($rowErrors[$rId])) {
                            $pushSentIds[] = $rId;
                            $stats['sent']++;
                        } else {
                            $errMsg = implode('; ', $rowErrors[$rId]);
                            $this->handleFailure($origRow, $errMsg, $stats, $comms);
                        }
                    }
                }

                if (!empty($pushSentIds)) {
                    $this->markBatchSent($pushSentIds);
                }

                continue;
            }

            // 2. Despacho de Email
            $emailSentIds = [];
            foreach ($rows as $row) {
                if (is_callable($heartbeatCallback)) {
                    $heartbeatCallback();
                }

                $rowId = (int) $row['id'];
                $destination = (string) $row['channel_key'];

                if (empty($destination)) {
                    $emailSentIds[] = $rowId;
                    $stats['sent']++;
                    continue;
                }

                if ($this->rateLimiter->isThrottled($channelName)) {
                    $this->defer($rowId, (int) $row['retry_count'], $comms->windowFor($channelName));
                    $stats['deferred']++;
                    continue;
                }

                $payload = !empty($row['payload']) ? json_decode((string) $row['payload'], true) ?? [] : [];

                try {
                    if ($channelCode === '1') {
                        $this->emailDriver->send(
                            $destination,
                            (string) $row['title'],
                            (string) $row['message'],
                            [
                                'payload'       => $payload,
                                'type_code'     => $row['type_code'] ?? ($payload['type_code'] ?? null),
                                'email_service' => $emailClient,
                                'keep_alive'    => true,
                            ]
                        );
                    }

                    $emailSentIds[] = $rowId;
                    $stats['sent']++;
                } catch (Throwable $e) {
                    $this->handleFailure($row, $e->getMessage(), $stats, $comms);
                }
            }

            if (!empty($emailSentIds)) {
                $this->markBatchSent($emailSentIds);
            }
        }

        return $stats;
    }

    /**
     * Consulta registros candidatos para despacho.
     *
     * @param int[]|null   $specificIds
     * @param list<string> $allowedChannelCodes
     */
    private function candidates(?array $specificIds, array $allowedChannelCodes): array
    {
        /** @var Comms $comms */
        $comms = config('Comms');
        $batchLimit = $comms->dispatchBatch ?? 100;
        $now = date('Y-m-d H:i:s');

        $builder = $this->db->table('sys_notification n')
            ->select('n.id, n.user_id, n.notification_type_id, n.channel_type, n.channel_key, n.title, n.message, n.payload, n.status, n.retry_count, n.max_retries, t.code as type_code')
            ->join('sys_notification_type t', 't.id = n.notification_type_id', 'left')
            ->whereIn('n.status', [NotificationModel::STATUS_PENDING, NotificationModel::STATUS_PROCESSING])
            ->whereIn('n.channel_type', $allowedChannelCodes);

        if (!empty($specificIds)) {
            $builder->whereIn('n.id', array_map('intval', $specificIds));
        } else {
            $builder->groupStart()
                ->where('next_attempt_at', null)
                ->orWhere("next_attempt_at <= {$this->db->escape($now)}")
                ->groupEnd();
        }

        return $builder
            ->orderBy('n.id', 'ASC')
            ->limit($batchLimit)
            ->get()
            ->getResultArray();
    }

    private function markBatchSent(array $ids): void
    {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($cleanIds)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('sys_notification')
            ->whereIn('id', $cleanIds)
            ->update([
                'status'     => NotificationModel::STATUS_SENT,
                'sent_at'    => $now,
                'updated_at' => $now,
            ]);
    }

    private function markSent(int $id): void
    {
        $this->markBatchSent([$id]);
    }

    private function defer(int $id, int $currentRetries, int $delaySeconds): void
    {
        $next = date('Y-m-d H:i:s', time() + $delaySeconds);
        $this->db->table('sys_notification')
            ->where('id', $id)
            ->update([
                'status'          => NotificationModel::STATUS_PENDING,
                'next_attempt_at' => $next,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
    }

    private function handleFailure(array $row, string $error, array &$stats, Comms $comms): void
    {
        $id = (int) $row['id'];
        $currentRetries = (int) $row['retry_count'] + 1;
        $maxRetries = (int) ($row['max_retries'] ?? $comms->maxRetries ?? 3);

        log_message('error', "[NotificationDispatcher] Fila [{$id}] falló (intento {$currentRetries}/{$maxRetries}): {$error}");

        if ($currentRetries >= $maxRetries) {
            $this->db->table('sys_notification')
                ->where('id', $id)
                ->update([
                    'status'        => NotificationModel::STATUS_DLQ, // 4 = Dead Letter Queue
                    'retry_count'   => $currentRetries,
                    'error_message' => mb_substr($error, 0, 500),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            $stats['dlq']++;
        } else {
            $base = $comms->baseBackoffSeconds ?? 60;
            $maxB = $comms->maxBackoffSeconds ?? 3600;
            $backoff = min($maxB, (int) ($base * (2 ** ($currentRetries - 1))));
            $next = date('Y-m-d H:i:s', time() + $backoff);

            $this->db->table('sys_notification')
                ->where('id', $id)
                ->update([
                    'status'          => NotificationModel::STATUS_PENDING,
                    'retry_count'     => $currentRetries,
                    'next_attempt_at' => $next,
                    'error_message'   => mb_substr($error, 0, 500),
                    'updated_at'      => date('Y-m-d H:i:s'),
                ]);
            $stats['failed']++;
        }
    }
}
