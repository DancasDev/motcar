<?php

namespace App\Controllers\V1\Access\Clients;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;
use App\Libraries\JWT\Token;

/**
 * Gestión de clientes API M2M (`gac_client`) en `v1/access/clients`.
 *
 * Genera credenciales automáticas, enmascara secretos en bitácora,
 * e invalida la matriz de accesos GAC (`purgeCache`) y tokens globales ante revocaciones.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\ClientModel';
    protected $schemaName = 'App\Schemas\Gac\ClientSchema';
    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create(): Response {
        $this->setCallback('itemSetValues', function ($item) {
            return array_merge($item, $this->model->generateCredentials());
        });
        $this->setCallback('itemAfterValidation', function ($item) {
            $result = $this->validationOfCompositeKeys($item);
            if ($result !== true) {
                return $result;
            }
        });
        $this->setCallback('itemSetBinnacleData', function ($id, $data) {
            $data['data']['client_secret'] = str_repeat('*', strlen($data['data']['client_secret']));
            return $data;
        });
        $this->setCallback('itemSuccess', function ($id, $item, $response) {
            $response['data']['client_id'] = $item['client_id'];
            $response['data']['client_secret'] = $item['client_secret'];
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
        $this->setCallback('itemSetValues', function ($item) {
            if (($item['update_credentials'] ?? false) === true) {
                $item = array_merge($item, $this->model->generateCredentials());
            }
            return $item;
        });
        $this->setCallback('itemAfterValidation', function ($item) {
            if (isset($item['client_secret'])) {
                $this->model->set('failed_attempt_count', '0')->set('failed_attempt_date', null);
            }
            if (array_key_exists('name', $item)) {
                $result = $this->validationOfCompositeKeys($item);
                if ($result !== true) {
                    return $result;
                }
            }
        });
        $this->setCallback('itemSetBinnacleData', function ($id, $data) {
            if (isset($data['data']['client_secret'])) {
                $data['data']['client_secret'] = str_repeat('*', strlen($data['data']['client_secret']));
            }
            return $data;
        });
        $toRevoke = [];
        $this->setCallback('itemSuccess', function ($id, $item, $response) use (&$toRevoke) {
            if (isset($item['client_secret'])) {
                $response['data']['client_id'] = $item['client_id'];
                $response['data']['client_secret'] = $item['client_secret'];
            }
            $critical = (isset($item['is_disabled']) && (string) $item['is_disabled'] === '1')
                     || isset($item['client_secret'])
                     || ($item['update_credentials'] ?? false) === true;
            if ($critical) {
                $toRevoke[] = $id;
            }
            return $response;
        });
        $this->setCallback('after', function ($response, $actionConfig) use (&$toRevoke, $id) {
            foreach ($toRevoke as $revId) {
                Token::revokeGlobal($revId, '2');
            }
            if (($response['affected'] ?? 0) > 0) {
                $this->purgeCache($id, $response);
            }
            return $response;
        });
        return $this->baseUpdate($id);
    }

    public function delete($id = null): Response {
        $this->setCallback('itemSuccess', function ($id, $item, $response) {
            Token::revokeGlobal($id, '2');
            return $response;
        });
        $this->setCallback('after', function ($response) use ($id) {
            if (($response['affected'] ?? 0) > 0) {
                $this->purgeCache($id, $response);
            }
            return $response;
        });
        return $this->baseDelete($id);
    }

    public function restore($id = null): Response {
        $this->setCallback('after', function ($response) use ($id) {
            if (($response['affected'] ?? 0) > 0) {
                $this->purgeCache($id, $response);
            }
            return $response;
        });
        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }
        return $this->baseRead($id, $returnArray);
    }

    private function validationOfCompositeKeys(array $item): bool|array {
        if ($this->compoundKeysValidator === null) {
            $this->compoundKeysValidator = new CompoundKeysValidator();
        }

        if (!$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['name'],
            pkField: $this->model->primaryKey
        )) {
            return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['name' => $this->compoundKeysValidator->getError()]];
        }

        return true;
    }

    private function purgeCache($clientId = null, array $response = []): void {
        $ids = $clientId !== null ? [(int) $clientId] : array_values(array_filter(array_column($response['data'] ?? [], 'id')));
        if (!empty($ids)) {
            service('authAccess')->purgeCacheBy('client', $ids);
        }
    }
}
