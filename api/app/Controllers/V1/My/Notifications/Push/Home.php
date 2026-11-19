<?php

namespace App\Controllers\V1\My\Notifications\Push;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Models\Gac\UserModel;
use App\Models\System\NotificationDeviceModel;
use App\Models\System\BinnacleModel;

/**
 * Gestión del canal Push (Firebase FCM) y dispositivos para el usuario autenticado.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\System\NotificationDeviceModel';
    protected $schemaName = 'App\Schemas\System\NotificationDeviceSchema';

    /**
     * Registra o actualiza un dispositivo push FCM para el usuario autenticado.
     */
    public function subscribe(): Response {
        if (!$this->isChannelActive()) {
            return $this->fail(lang('My.push.channel_disabled'), 403, 'MY_PUSH_CHANNEL_DISABLED');
        }

        $input = $this->getInput();
        if ($input instanceof Response) {
            return $input;
        }

        $userId = $this->authUserId();
        $input['user_id'] = $userId;

        // Validar directamente con las reglas declaradas en el modelo
        if (!$this->validateData($input, $this->model->getValidationRules())) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            $device = $this->model->registerOrUpdateDevice(
                $userId,
                $this->validator->getValidated(),
                $this->request->getIPAddress()
            );

            // Asegurar que el canal '2' (Push) quede activo en las preferencias del usuario
            $userModel = new UserModel();
            $userRow = $userModel->select('id, notification_channels')->find($userId);
            if ($userRow) {
                $newChannels = $userModel->addNotificationChannels($userRow['notification_channels'], ['2']);
                if ($newChannels !== $userRow['notification_channels']) {
                    $userModel->skipValidation(true)->protect(false)->update($userId, [
                        'notification_channels' => $newChannels,
                        'updated_at'            => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Registrar en bitácora
            (new BinnacleModel())->log(
                'user',
                $userId,
                'push_device',
                (string) $device['id'],
                'create',
                date('Y-m-d H:i:s'),
                $this->request->getIPAddress(),
                [
                    'device_id'   => $device['id'],
                    'device_type' => $device['device_type'],
                    'device_name' => $device['device_name'],
                ],
                service('authToken')->getPayloadValue('jti')
            );

            return $this->respond([
                'status'   => 201,
                'data'     => [
                    'id'       => (int) $device['id'],
                    'affected' => true,
                ],
                'messages' => [
                    'success' => lang('My.push.device_registered'),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return $this->fail(lang('My.push.register_failed'), 500, 'MY_PUSH_REGISTER_FAILED');
        }
    }

    /**
     * Lista todos los dispositivos push registrados del usuario autenticado (con soporte DQB).
     */
    public function devices(): Response {
        if (!$this->isChannelActive()) {
            return $this->fail(lang('My.push.channel_disabled'), 403, 'MY_PUSH_CHANNEL_DISABLED');
        }

        return $this->_read();
    }

    /**
     * Consulta el detalle de un dispositivo push específico.
     */
    public function show($id = null): Response {
        if (!$this->isChannelActive()) {
            return $this->fail(lang('My.push.channel_disabled'), 403, 'MY_PUSH_CHANNEL_DISABLED');
        }

        return $this->_read($id);
    }

    /**
     * Consulta el estado general de suscripción Push del usuario.
     */
    public function status(): Response {
        if (!$this->isChannelActive()) {
            return $this->fail(lang('My.push.channel_disabled'), 403, 'MY_PUSH_CHANNEL_DISABLED');
        }

        $userId = $this->authUserId();
        $activeCount = $this->model->where('user_id', $userId)
            ->where('notifications_enabled', 1)
            ->where('deleted_at', null)
            ->countAllResults();

        $totalCount = $this->model->where('user_id', $userId)
            ->where('deleted_at', null)
            ->countAllResults();

        return $this->respond([
            'status' => 200,
            'data'   => [
                'status'          => $activeCount > 0 ? 'active' : 'inactive',
                'active_devices'  => $activeCount,
                'total_devices'   => $totalCount,
                'max_devices'     => NotificationDeviceModel::MAX_DEVICES_PER_USER,
                'provider'        => 'fcm',
            ],
        ], 200);
    }

    /**
     * Actualiza la configuración de un dispositivo (nombre o estado de notificaciones).
     */
    public function updateDevice($id = null): Response {
        if (!$this->isChannelActive()) {
            return $this->fail(lang('My.push.channel_disabled'), 403, 'MY_PUSH_CHANNEL_DISABLED');
        }

        $input = $this->getInput();
        if ($input instanceof Response) {
            return $input;
        }

        $userId = $this->authUserId();
        $device = $this->model->where('id', $id)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->first();

        if (!$device) {
            return $this->fail(lang('My.push.device_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $updateData = [];
        if (isset($input['device_name'])) {
            $updateData['device_name'] = trim((string) $input['device_name']);
        }
        if (isset($input['notifications_enabled'])) {
            $updateData['notifications_enabled'] = (int) $input['notifications_enabled'] ? 1 : 0;
        }

        if (empty($updateData)) {
            return $this->respond([
                'status'   => 200,
                'data'     => ['id' => (int) $id, 'affected' => false],
                'messages' => ['success' => lang('CrudTraits.update.no_changes')],
            ], 200);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $this->model->skipValidation(true)->update($id, $updateData);

        // Registrar en bitácora
        (new BinnacleModel())->log(
            'user',
            $userId,
            'push_device',
            (string) $id,
            'update',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            $updateData,
            service('authToken')->getPayloadValue('jti')
        );

        return $this->respond([
            'status'   => 200,
            'data'     => ['id' => (int) $id, 'affected' => true],
            'messages' => ['success' => lang('My.push.device_updated')],
        ], 200);
    }

    /**
     * Elimina/desvincula un dispositivo del usuario.
     */
    public function deleteDevice($id = null): Response {
        if (!$this->isChannelActive()) {
            return $this->fail(lang('My.push.channel_disabled'), 403, 'MY_PUSH_CHANNEL_DISABLED');
        }

        $userId = $this->authUserId();
        $device = $this->model->where('id', $id)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->first();

        if (!$device) {
            return $this->fail(lang('My.push.device_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $this->model->delete($id);

        // Registrar en bitácora
        (new BinnacleModel())->log(
            'user',
            $userId,
            'push_device',
            (string) $id,
            'delete',
            date('Y-m-d H:i:s'),
            $this->request->getIPAddress(),
            [
                'device_name' => $device['device_name'],
            ],
            service('authToken')->getPayloadValue('jti')
        );

        // Si ya no quedan dispositivos activos, retirar canal '4' del usuario
        $remaining = $this->model->where('user_id', $userId)->where('notifications_enabled', 1)->where('deleted_at', null)->countAllResults();
        if ($remaining === 0) {
            $userModel = new UserModel();
            $user = $userModel->select('id, notification_channels')->find($userId);
            if ($user) {
                $newChannels = $userModel->removeNotificationChannels($user['notification_channels'], ['2']);
                $userModel->skipValidation(true)->protect(false)->update($userId, [
                    'notification_channels' => $newChannels,
                    'updated_at'            => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $this->respond([
            'status'   => 200,
            'data'     => ['id' => (int) $id, 'affected' => true],
            'messages' => ['success' => lang('My.push.device_deleted')],
        ], 200);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }
        $userId = $this->authUserId();

        $this->setConfig('read:filtersDefault', [
            ['user_id', $userId],
        ]);
        $this->setConfig('read:order', ['last_active_at' => 'DESC', 'id' => 'DESC']);
        $this->setCallback('read:schema', function ($schema) {
            $schema->addFieldConfig('push_token', [
                'read_disabled' => true,
            ]);
        });

        return $this->baseRead($id, $returnArray);
    }

    private function isChannelActive(): bool {
        return config('Comms')->isNotificationChannelEnabled('push');
    }

    private function authUserId(): int {
        return (int) service('authToken')->getPayloadValue('sub');
    }
}