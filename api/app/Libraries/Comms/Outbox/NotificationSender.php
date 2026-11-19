<?php

namespace App\Libraries\Comms\Outbox;

use App\Libraries\Comms\Exceptions\NoActiveChannelException;
use App\Models\Global\LanguageModel;
use App\Models\System\NotificationDeviceModel;
use App\Models\System\NotificationTypeModel;
use CodeIgniter\Database\BaseConnection;
use Config\Comms;

/**
 * Orquestador de preparación y registro por lote de notificaciones en el Outbox (sys_notification).
 * Responsabilidad única: Consultar destinatarios, resolver plantillas e insertar en base de datos.
 */
class NotificationSender
{
    private BaseConnection $db;
    private NotificationOutbox $outbox;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db     = $db ?? db_connect();
        $this->outbox = new NotificationOutbox($this->db);
    }

    /**
     * Registra las notificaciones para uno o múltiples usuarios en sys_notification.
     *
     * @param int|int[]            $userIds  ID o lista de IDs de destinatarios
     * @param string               $typeCode Código del tipo (sys_notification_type.code)
     * @param array<string, mixed> $data     Variables para interpolación y payload
     *
     * @throws NoActiveChannelException
     */
    public function register(
        int|array $userIds,
        string $typeCode,
        array $data = []
    ): NotificationResult {
        $result = new NotificationResult();
        $targetIds = array_values(array_unique(array_filter(array_map('intval', (array) $userIds))));
        if (empty($targetIds)) {
            return $result;
        }

        // 1. Cargar tipo de notificación desde caché del modelo
        $typeModel = new NotificationTypeModel($this->db);
        $types     = $typeModel->getByCache();
        $typeRow   = $types[$typeCode] ?? null;

        if (!$typeRow || (string) $typeRow['is_disabled'] === '1') {
            log_message('error', "[NotificationSender] Notification type [{$typeCode}] not found or disabled.");
            throw new NoActiveChannelException($targetIds[0], "Notification type [{$typeCode}] not found or disabled.");
        }

        $typeChannels = ($typeRow['channels'] !== null && $typeRow['channels'] !== '')
            ? explode(',', (string) $typeRow['channels'])
            : ['0', '1', '2'];

        // 2. Canales habilitados globalmente en config('Comms')
        /** @var Comms $comms */
        $comms = config('Comms');
        $globalChannels = $comms->notificationChannels ?? ['inapp', 'email', 'push'];

        // 3. Cargar usuarios destinatarios (1 query)
        $users = $this->db->table('gac_user')
            ->select('id, first_name, last_name, email, email_verified_date, notification_channels, language_id, is_disabled')
            ->whereIn('id', $targetIds)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        if (empty($users)) {
            if (count($targetIds) === 1) {
                log_message('error', "[NotificationSender] User [{$targetIds[0]}] not found or inactive.");
                throw new NoActiveChannelException($targetIds[0], "User [{$targetIds[0]}] not found or inactive.");
            }
            return $result;
        }

        // 4. Precarga de usuarios con dispositivos push activos (1 query si aplica)
        $usersWithPush = [];
        if (in_array('2', $typeChannels, true) && in_array('push', $globalChannels, true)) {
            $devices = (new NotificationDeviceModel($this->db))
                ->select('user_id')
                ->whereIn('user_id', array_column($users, 'id'))
                ->where('notifications_enabled', 1)
                ->where('is_disabled', '0')
                ->where('deleted_at', null)
                ->findAll();

            foreach ($devices as $d) {
                $usersWithPush[(int) $d['user_id']] = true;
            }
        }

        $allRows = [];
        $queuedChannels = [];

        // 5. Construir datos por cada usuario y acumular filas para inserción en lote
        foreach ($users as $user) {
            if ((string) ($user['is_disabled'] ?? '0') === '1') {
                continue;
            }

            $userId = (int) $user['id'];
            $userChannels = $user['notification_channels'] !== null && $user['notification_channels'] !== ''
                ? explode(',', (string) $user['notification_channels'])
                : [];

            $enabledChannels = [];
            $channelKeys = [];

            // In-App (canal '0')
            if (in_array('0', $typeChannels, true) && in_array('inapp', $globalChannels, true)) {
                $enabledChannels[] = '0';
            }

            // Email (canal '1')
            if (in_array('1', $typeChannels, true) && in_array('email', $globalChannels, true) && in_array('1', $userChannels, true) && !empty($user['email']) && !empty($user['email_verified_date'])) {
                $enabledChannels[] = '1';
                $channelKeys['email'] = $user['email'];
            }

            // Push (canal '2')
            if (in_array('2', $typeChannels, true) && in_array('push', $globalChannels, true) && in_array('2', $userChannels, true) && isset($usersWithPush[$userId])) {
                $enabledChannels[] = '2';
                $channelKeys['push'] = null;
            }

            if (empty($enabledChannels)) {
                continue;
            }

            $locale = $this->resolveLocale($user['language_id'] ?? null);
            $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $renderedContent = $this->renderContent($typeRow, $data, $locale, $userName);

            $userRows = $this->outbox->buildRows(
                $userId,
                $renderedContent,
                $enabledChannels,
                $channelKeys,
                $data
            );

            foreach ($userRows as $r) {
                $allRows[] = $r;
            }

            foreach (array_diff($enabledChannels, ['0']) as $qCh) {
                $queuedChannels[$qCh] = true;
            }
        }

        // 6. Inserción en lote (1 query para todas las notificaciones del lote)
        $allExternalIds = $this->outbox->insertBatch($allRows);

        $result->insertedCount = count($allRows);
        $result->channelsQueued = array_keys($queuedChannels);
        $result->externalIds = $allExternalIds;

        return $result;
    }

    /**
     * Resuelve el locale a partir de language_id o configuración global.
     */
    protected function resolveLocale(?int $languageId): string
    {
        return (new LanguageModel($this->db))->resolveLocale($languageId);
    }


    /**
     * Renderiza el título, mensaje y action_url interpolados con los datos del evento.
     */
    protected function renderContent(array $typeRow, array $data, string $locale, string $userName): array
    {
        $typeId = (int) $typeRow['id'];
        $typeCode = (string) $typeRow['code'];

        $allData = array_merge($data, [
            'user_name' => $userName,
            'app_name'  => config('App')->siteName ?? 'API System',
            'app_url'   => rtrim(config('App')->baseURL ?? '', '/'),
            'date'      => date('d/m/Y'),
            'time'      => date('H:i'),
        ]);

        $titleTpl   = $data['title'] ?? lang("Notifications.{$typeCode}.title", [], $locale);
        $rawMessage = lang("Notifications.{$typeCode}.inapp", [], $locale);
        if (empty($rawMessage) || $rawMessage === "Notifications.{$typeCode}.inapp") {
            $rawMessage = lang("Notifications.{$typeCode}.push", [], $locale);
        }
        if (empty($rawMessage) || $rawMessage === "Notifications.{$typeCode}.push") {
            $rawMessage = lang("Notifications.{$typeCode}.email", [], $locale);
        }

        $messageTpl = $data['message'] ?? $rawMessage;

        if (empty($titleTpl) || $titleTpl === "Notifications.{$typeCode}.title") {
            $titleTpl = ucfirst(str_replace('_', ' ', $typeCode));
        }

        if (empty($messageTpl) || str_starts_with((string) $messageTpl, 'Notifications.')) {
            $messageTpl = $titleTpl;
        }

        $title   = NotificationInterpolator::interpolate((string) $titleTpl, $allData);
        $message = NotificationInterpolator::interpolate((string) $messageTpl, $allData);

        $actionUrl = null;
        $rawActionUrl = $data['action_url'] ?? ($data['url'] ?? ($typeRow['action_url'] ?? null));
        if (!empty($rawActionUrl)) {
            $actionUrl = NotificationInterpolator::interpolate((string) $rawActionUrl, $allData);
        }

        return [
            'type_id'    => $typeId,
            'title'      => $title,
            'message'    => $message,
            'action_url' => $actionUrl,
        ];
    }
}
