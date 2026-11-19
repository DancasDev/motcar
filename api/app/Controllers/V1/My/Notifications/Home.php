<?php

namespace App\Controllers\V1\My\Notifications;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Models\System\NotificationModel;

/**
 * Notificaciones del usuario autenticado (campanita In-app, channel_type '0').
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\System\NotificationModel';
    protected $schemaName = 'App\Schemas\System\NotificationSchema';

    public function index(): Response {
        return $this->_read();
    }

    public function show($id = null): Response {
        return $this->_read($id);
    }

    public function unreadCount(): Response {
        $userId = service('authToken')->getPayloadValue('sub');
        $count = $this->model
            ->where('user_id', $userId)
            ->where('channel_type', NotificationModel::CHANNEL_INAPP)
            ->where('status', NotificationModel::STATUS_SENT)
            ->where('read_at', null)
            ->where('deleted_at', null)
            ->countAllResults();

        return $this->respond(['status' => 200, 'data' => ['unread' => $count]], 200);
    }

    public function markRead($id = null): Response {
        $userId = (int) service('authToken')->getPayloadValue('sub');

        $row = $this->model
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('channel_type', NotificationModel::CHANNEL_INAPP)
            ->where('deleted_at', null)
            ->limit(1)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return $this->fail(lang('My.notifications.not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $this->model->skipValidation(true)->protect(false)
            ->update($row['id'], [
                'status'     => NotificationModel::STATUS_READ,
                'read_at'    => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $this->respond([
            'status'   => 200,
            'data'     => [
                'id'       => (int) $row['id'],
                'affected' => true,
                'status'   => NotificationModel::STATUS_READ,
            ],
            'messages' => [
                'success' => lang('My.notifications.marked_as_read'),
            ],
        ], 200);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }
        $userId = service('authToken')->getPayloadValue('sub');

        $this->setConfig('read:filtersDefault', [
            ['user_id', $userId],
            ['channel_type', '0'],
        ]);
        $this->setConfig('read:order', ['id' => 'DESC']);
        $this->setCallback('read:schema', function ($schema) {
            $disabledConf = ['read_disabled' => true, 'order_disabled' => true, 'filter_disabled' => true, 'group_by_disabled' => true];
            $schema->addFieldConfig('user_id', $disabledConf);
        });
        return $this->baseRead($id, $returnArray);
    }
}