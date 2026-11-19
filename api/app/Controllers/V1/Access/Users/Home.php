<?php

namespace App\Controllers\V1\Access\Users;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\JWT\Token;
use App\Libraries\Metadata\Metadata;

/**
 * Gestión de usuarios del sistema (`gac_user`) en `v1/access/users`.
 *
 * - Despacha notificación de bienvenida (`user_welcome`) tras creación exitosa.
 * - Enmascara `password` en bitácora y bloquea mutación directa de credenciales en update.
 * - Invalida tokens globales ante deshabilitación, cambio de password o eliminación.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\UserModel';
    protected $schemaName = 'App\Schemas\Gac\UserSchema';

    public function create(): Response {
        $metadata = new Metadata('gac_user');
        $this->setCallback('itemAfterValidation', function ($item) use ($metadata) {
            return $metadata->process($item);
        });

        $this->setCallback('itemSetBinnacleData', function ($id, $data) {
            $data['data']['password'] = str_repeat('*', strlen($data['data']['password']));
            return $data;
        });

        $this->setCallback('after', function ($response, $actionConfig) {
            $userIds = array_values(array_filter(array_column($response['data'] ?? [], 'id')));
            if (!empty($userIds)) {
                service('notification')->send($userIds, 'user_welcome');
            }
            return $response;
        });

        return $this->baseCreate();
    }

    public function index(): Response {
        return $this->_read();
    }

    public function show($id = null): Response {
        return $this->_read($id);
    }

    public function update($id = null): Response {
        $metadata = new Metadata('gac_user');
        $this->setCallback('itemAfterValidation', function ($item) use ($metadata) {
            return $metadata->process($item, (int) $item['id']);
        });

        $this->setCallback('itemSetBinnacleData', function ($id, $data) {
            if (isset($data['data']['password'])) {
                $data['data']['password'] = str_repeat('*', strlen($data['data']['password']));
            }
            return $data;
        });

        $toRevoke = [];
        $this->setCallback('itemSuccess', function ($id, $item, $response) use (&$toRevoke) {
            $critical = (isset($item['is_disabled']) && (string) $item['is_disabled'] === '1')
                     || array_key_exists('password', $item);
            if ($critical) {
                $toRevoke[] = $id;
            }
            return $response;
        });

        $this->setCallback('after', function ($response, $actionConfig) use (&$toRevoke) {
            foreach ($toRevoke as $id) {
                Token::revokeGlobal($id, '1');
            }
            return $response;
        });

        return $this->baseUpdate($id);
    }

    public function delete($id = null): Response {
        $this->setCallback('itemSuccess', function ($id, $item, $response) {
            Token::revokeGlobal($id, '1');
            return $response;
        });
        return $this->baseDelete($id);
    }

    public function restore($id = null): Response {
        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }
        if (defined("{$this->schemaName}::FIELDS_CALCULATED")) {
            $this->setConfig('read:fieldsCalculated', $this->schema::FIELDS_CALCULATED);
            $this->setCallback('read:fieldsCalculated', $this->schema::fieldsCalculatedCallback(...));
        }

        return $this->baseRead($id, $returnArray);
    }
}
