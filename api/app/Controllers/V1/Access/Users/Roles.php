<?php

namespace App\Controllers\V1\Access\Users;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;
use DancasDev\GAC\Utils as GacUtils;

/**
 * Asignación de roles a usuarios (`gac_role_entity`) en `v1/access/users/{userId}/roles`.
 *
 * Scoping automático por `entity_type=1` y `userId`, validación de unicidad compuesta
 * (rol único y prioridad única por usuario) y purga de caché GAC (`purgeCache`).
 */
class Roles extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\RoleEntityModel';
    protected $schemaName = 'App\Schemas\Gac\RoleEntitySchema';
    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create($userId = null): Response {
        $this->setCallback('itemSetValues', function ($item) use ($userId) {
            $item['entity_type'] = '1';
            $item['entity_id']   = $userId;
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
        $this->setCallback('after', function ($response) use ($userId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($userId);
            }
        });
        return $this->baseCreate();
    }

    public function index($userId = null): Response {
        return $this->_read($userId);
    }

    public function show($userId = null, $id = null): Response {
        return $this->_read($userId, $id);
    }

    public function update($userId = null, $id = null): Response {
        $this->setCallback('setRules', function ($rules) {
            unset($rules['role_id'], $rules['entity_type'], $rules['entity_id']);
            return $rules;
        });
        $this->setCallback('itemAfterValidation', function ($item) use ($userId) {
            if (array_key_exists('scope_path', $item)) {
                if ($item['scope_path'] === '') {
                    $item['scope_path'] = null;
                }
                if (!GacUtils::scopeValidate($item['scope_path'])) {
                    return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['scope_path' => lang('Validation.scopeInvalid')]];
                }
            }
            if (array_key_exists('priority', $item)) {
                $result = $this->validationOfCompositeKeys(array_merge($item, ['entity_type' => '1', 'entity_id' => $userId]), true);
                if ($result !== true) {
                    return $result;
                }
            }
            $this->model->where($this->getDirectRelationCriteria($userId));
        });
        $this->setCallback('after', function ($response) use ($userId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($userId);
            }
        });
        return $this->baseUpdate($id);
    }

    public function delete($userId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) use ($userId) {
            $this->model->where($this->getDirectRelationCriteria($userId));
        });
        $this->setCallback('after', function ($response) use ($userId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($userId);
            }
        });
        return $this->baseDelete($id);
    }

    public function restore($userId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) use ($userId) {
            $this->model->where($this->getDirectRelationCriteria($userId));
        });
        $this->setCallback('after', function ($response) use ($userId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($userId);
            }
        });
        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($userId = null, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($userId, true));
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

        // uk_1: (role_id, entity_type, entity_id) — solo aplica en creación
        if (!$isUpdate && !$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['role_id', 'entity_type', 'entity_id'],
            pkField: $this->model->primaryKey
        )) {
            return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['role_id' => $this->compoundKeysValidator->getError()]];
        }

        // uk_2: (entity_type, entity_id, priority) — aplica en creación y actualización
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

    private function getDirectRelationCriteria($userId, bool $isRead = false): array {
        if ($isRead) {
            return [['entity_type', '1'], ['entity_id', $userId]];
        }
        return ['entity_type' => '1', 'entity_id' => $userId];
    }

    private function purgeCache($userId = null): void {
        if ($userId) {
            service('authAccess')->purgeCacheBy('user', [(int) $userId]);
        }
    }
}
