<?php

namespace App\Controllers\V1\My\Notifications\Channels;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Models\Gac\UserModel;
use App\Models\System\NotificationDeviceModel;
use App\Models\System\BinnacleModel;

/**
 * Canales de notificación configurables del usuario (gac_user.notification_channels).
 * Canales externos configurables: 1: Email, 2: Push.
 * (In-App [0] es intrínseco y permanente, no se gestiona como preferencia externa).
 */
class Home extends BaseController {
    protected $modelName = 'App\Models\Gac\UserModel';

    private const CHANNELS = ['1', '2'];

    public function index(): Response {
        $user = $this->currentUser();

        $enabled = $user['notification_channels'] !== null
            ? explode(',', (string) $user['notification_channels'])
            : [];

        $data = [];
        foreach (self::CHANNELS as $channel) {
            $data[] = [
                'channel'     => $channel,
                'available'   => $this->channelAvailable($channel, $user),
                'enabled'     => in_array($channel, $enabled, true),
                'channel_key' => $this->channelKey($channel, $user),
            ];
        }

        return $this->respond(['status' => 200, 'data' => $data], 200);
    }

    public function update($id = null): Response {
        $input = $this->getInput();
        if ($input instanceof Response) {
            return $input;
        }

        // Se exige estrictamente el cuerpo envuelto con la clave 'notification_channels'
        if (!is_array($input) || !array_key_exists('notification_channels', $input)) {
            return $this->failValidationErrors([
                'notification_channels' => lang('My.channels.invalid_format'),
            ]);
        }

        $rawChannels = $input['notification_channels'];
        if (is_array($rawChannels)) {
            $rawChannels = implode(',', $rawChannels);
        } elseif ($rawChannels === '') {
            $rawChannels = null;
        }

        // Validar con la regla definida en UserModel (canales 1 y 2)
        $validationRules = [
            'notification_channels' => $this->model->getValidationRules()['notification_channels'] ?? 'permit_empty|string|regex_match[/^(?:[12](?:,[12])*)$/]',
        ];

        if (!$this->validateData(['notification_channels' => $rawChannels], $validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $user = $this->currentUser();

        // Si se envía null o vacío, deshabilitar todos los canales configurables del usuario
        if ($rawChannels === null) {
            $this->model->skipValidation(true)->protect(false)
                ->update($user['id'], [
                    'notification_channels' => null,
                    'updated_at'            => date('Y-m-d H:i:s'),
                ]);

            (new BinnacleModel())->log(
                'user',
                (int) $user['id'],
                'notification_channels',
                (string) $user['id'],
                'update',
                date('Y-m-d H:i:s'),
                $this->request->getIPAddress(),
                [
                    'channels' => [],
                ],
                service('authToken')->getPayloadValue('jti')
            );

            return $this->index();
        }

        $requested = explode(',', (string) $rawChannels);

        // Operación Todo o Nada: si algún canal solicitado no está disponible, se rechaza la transacción entera
        $unavailableChannels = [];
        foreach ($requested as $channel) {
            if (!$this->channelAvailable($channel, $user)) {
                $unavailableChannels[] = $channel;
            }
        }

        if (!empty($unavailableChannels)) {
            return $this->failValidationErrors([
                'notification_channels' => lang('My.channels.channel_unavailable'),
            ]);
        }

        $channelsCsv = implode(',', array_values(array_unique($requested)));

        $this->model->db->transStart();

        $this->model->skipValidation(true)->protect(false)
            ->update($user['id'], [
                'notification_channels' => $channelsCsv,
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

        // Registrar en bitácora de auditoría
        (new BinnacleModel())->log(
            'user',
            (int) $user['id'],
            'notification_channels',
            (string) $user['id'],
            'update',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            [
                'channels' => $requested,
            ],
            service('authToken')->getPayloadValue('jti')
        );

        $this->model->db->transComplete();

        if ($this->model->db->transStatus() === false) {
            return $this->fail(lang('CrudTraits.update.error') ?? 'Error al actualizar los canales de notificación.', 500, 'CHANNELS_UPDATE_ERROR');
        }

        return $this->index();
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function currentUser(): array {
        $userId = service('authToken')->getPayloadValue('sub');
        return $this->model
            ->select('id, email, email_verified_date, notification_channels, language_id')
            ->where('id', $userId)
            ->limit(1)
            ->get()
            ->getRowArray() ?: [];
    }

    private function channelAvailable(string $channel, array $user): bool {
        if (!config('Comms')->isNotificationChannelEnabled($channel)) {
            return false;
        }

        return match ($channel) {
            '1' => !empty($user['email']) && !empty($user['email_verified_date']),
            '2' => (new NotificationDeviceModel())->where('user_id', $user['id'])->where('notifications_enabled', 1)->where('deleted_at', null)->countAllResults() > 0,
            default => false,
        };
    }

    private function channelKey(string $channel, array $user): ?string {
        return match ($channel) {
            '1' => $user['email'] ?? null,
            '2' => 'push_devices',
            default => null,
        };
    }
}