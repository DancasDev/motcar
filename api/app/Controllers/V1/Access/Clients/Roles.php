<?php

namespace App\Controllers\V1\Access\Clients;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;
use DancasDev\GAC\Utils as GacUtils;

/**
 * Asignación de roles a clientes API (`gac_role_entity`) en `v1/access/clients/{clientId}/roles`.
 *
 * Scoping automático por `entity_type=2` y `clientId`, validación de unicidad compuesta
 * (rol único y prioridad única por cliente) y purga de caché GAC (`purgeCache`).
 */
class Roles extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\RoleEntityModel';
    protected $schemaName = 'App\Schemas\Gac\RoleEntitySchema';
    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create($clientId = null): Response {
        $this->setCallback('itemSetValues', function ($item) use ($clientId) {
            $item['entity_type'] = '2';
            $item['entity_id']   = $clientId;
            if (!array_key_exists('scope_path', $item) || $item['scope_path'] === '') {
                $item['scope_path'] = null;
            }
            return $item;
        });
        $this->setCallback('itemAfterValidation', function ($item) {
            $result = $this->validationOfCompositeKeys($item);
            if ($result !== true) {
                return $result;
            }
            if (!GacUtils::scopeValidate($item['scope_path'] ?? null)) {
                return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['scope_path' => lang('Validation.scopeInvalid')]];
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
            unset($rules['role_id'], $rules['entity_type'], $rules['entity_id']);
            return $rules;
        });
        $this->setCallback('itemAfterValidation', function ($item) use ($clientId) {
            if (array_key_exists('scope_path', $item)) {
                if ($item['scope_path'] === '') {
                    $item['scope_path'] = null;
                }
                if (!GacUtils::scopeValidate($item['scope_path'])) {
                    return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['scope_path' => lang('Validation.scopeInvalid')]];
                }
            }
            if (array_key_exists('priority', $item)) {
                $result = $this->validationOfCompositeKeys(array_merge($item, ['entity_type' => '2', 'entity_id' => $clientId]), true);
                if ($result !== true) {
                    return $result;
                }
            }
            $this->model->where($this->getDirectRelationCriteria($clientId));
        });
        $this->setCallback('after', function ($response) use ($clientId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($clientId);
            }
        });
        return $this->baseUpdate($id);
    }

    public function delete($clientId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) use ($clientId) {
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
        $this->setCallback('itemAfterValidation', function ($item) use ($clientId) {
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
        });

        return $this->baseRead($id, $returnArray);
    }

    private function validationOfCompositeKeys(array $item, bool $isUpdate = false): bool|array {
        if ($this->compoundKeysValidator === null) {
            $this->compoundKeysValidator = new CompoundKeysValidator();
        }

        if (!$isUpdate && !$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['role_id', 'entity_type', 'entity_id'],
            pkField: $this->model->primaryKey
        )) {
            return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['role_id' => $this->compoundKeysValidator->getError()]];
        }

        if (!$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['entity_type', 'entity_id', 'priority'],
            pkField: $this->model->primaryKey
        )) {
            return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['priority' => $this->compoundKeysValidator->getError()]];
        }

        return true;
    }

    private function getDirectRelationCriteria($clientId, bool $isRead = false): array {
        if ($isRead) {
            return [['entity_type', '2'], ['entity_id', $clientId]];
        }
        return ['entity_type' => '2', 'entity_id' => $clientId];
    }

    private function purgeCache($clientId = null): void {
        if ($clientId) {
            service('authAccess')->purgeCacheBy('client', [(int) $clientId]);
        }
    }
}
