<?php

namespace App\Controllers\V1\Access\Clients;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;
use DancasDev\GAC\Utils as GacUtils;

/**
 * Gestión de permisos asociados a clientes API (`gac_permission`) en `v1/access/clients/{clientId}/permissions`.
 *
 * Scoping automático por `entity_type=2` y `clientId`, validación de unicidad y colisión de scopes,
 * validación de payload según módulo, y purga de caché GAC (`purgeCache`).
 */
class Permissions extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\PermissionModel';
    protected $schemaName = 'App\Schemas\Gac\PermissionSchema';
    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create($clientId = null): Response {
        $this->setCallback('itemSetValues', function ($item) use ($clientId) {
            $item['entity_type'] = '2';
            $item['entity_id']   = $clientId;
            $item['scope_path'] ??= '*';
            return $item;
        });
        $this->setCallback('itemAfterValidation', function ($item) {
            if (!GacUtils::scopeValidate($item['scope_path'] ?? null)) {
                return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['scope_path' => lang('Validation.scopeInvalid')]];
            }
            if (($item['scope_path'] ?? '*') !== '*') {
                $existing = $this->model->db->table($this->model->table)
                    ->select('scope_path')
                    ->where('entity_type', $item['entity_type'])
                    ->where('entity_id', $item['entity_id'])
                    ->where('module_id', $item['module_id'])
                    ->where('deleted_at IS NULL')
                    ->get()->getResultArray();
                if (GacUtils::scopeHasCollision($existing, $item['scope_path'])) {
                    return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['scope_path' => lang('Validation.scopeCollision')]];
                }
            }
            $result = $this->validationOfCompositeKeys($item);
            if ($result !== true) {
                return $result;
            }
            $result = $this->model->validatePayload($item);
            if ($result !== true) {
                return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => $result];
            }
        });
        $this->setCallback('after', function ($response) use ($clientId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($clientId);
            }
        });
        return $this->baseCreate();
    }

    public function index($clientId = null): Response {
        return $this->_read($clientId);
    }

    public function show($clientId = null, $id = null): Response {
        return $this->_read($clientId, $id);
    }

    public function update($clientId = null, $id = null): Response {
        $this->setCallback('setRules', function ($rules) {
            unset($rules['entity_type'], $rules['entity_id'],
                $rules['module_id']);
            return $rules;
        });
        $this->setCallback('itemAfterValidation', function ($item) use ($clientId, $id) {
            $this->model->where($this->getDirectRelationCriteria($clientId));
            if (array_key_exists('scope_path', $item)) {
                if (!GacUtils::scopeValidate($item['scope_path'])) {
                    return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['scope_path' => lang('Validation.scopeInvalid')]];
                }
                $result = $this->validationOfCompositeKeys($item);
                if ($result !== true) {
                    return $result;
                }
            }
            if (array_key_exists('payload', $item)) {
                $result = $this->model->validatePayload($item, (int) $id);
                if ($result !== true) {
                    return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => $result];
                }
            }
        });
        $this->setCallback('after', function ($response) use ($clientId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($clientId);
            }
        });
        return $this->baseUpdate($id);
    }

    public function delete($clientId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) use($clientId) {
            $this->model->where($this->getDirectRelationCriteria($clientId));
        });
        $this->setCallback('after', function ($response) use ($clientId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($clientId);
            }
        });
        return $this->baseDelete($id);
    }

    public function restore($clientId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) use($clientId) {
            $this->model->where($this->getDirectRelationCriteria($clientId));
        });
        $this->setCallback('after', function ($response) use ($clientId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($clientId);
            }
        });
        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($clientId = null, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($clientId, true));
        $this->setCallback('read:schema', function ($schema) {
            $disabledConf = ['read_disabled' => true, 'order_disabled' => true, 'filter_disabled' => true, 'group_by_disabled' => true];
            $schema->addFieldConfig('entity_type', $disabledConf);
            $schema->addFieldConfig('entity_id', $disabledConf);
            $schema->addFieldConfig('scope_path', $disabledConf);
        });

        return $this->baseRead($id, $returnArray);
    }

    private function validationOfCompositeKeys(array $item) : bool|array {
        if ($this->compoundKeysValidator === null) {
            $this->compoundKeysValidator = new CompoundKeysValidator();
        }

        if (!$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['entity_type', 'entity_id', 'module_id', 'scope_path'],
            pkField: $this->model->primaryKey
        )) {
            return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['module_id' => $this->compoundKeysValidator->getError()]];
        }

        return true;
    }

    private function purgeCache($clientId = null): void {
        if ($clientId) {
            service('authAccess')->purgeCacheBy('client', [(int) $clientId]);
        }
    }

    private function getDirectRelationCriteria($clientId, bool $isRead = false): array {
        if ($isRead) {
            return [['entity_type', '2'], ['entity_id', $clientId]];
        }
        return ['entity_type' => '2', 'entity_id' => $clientId];
    }
}
