<?php

namespace App\Controllers\V1\Access\Users;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;
use DancasDev\GAC\Utils as GacUtils;

/**
 * Gestión de permisos específicos de usuario (`gac_permission`) en `v1/access/users/{userId}/permissions`.
 *
 * Scoping automático por `entity_type=1` y `userId`, validación de unicidad y colisión de scopes,
 * validación de payload según módulo, y purga de caché GAC (`purgeCache`).
 */
class Permissions extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\PermissionModel';
    protected $schemaName = 'App\Schemas\Gac\PermissionSchema';
    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create($userId = null): Response {
        $this->setCallback('itemSetValues', function ($item) use ($userId) {
            $item['entity_type'] = '1';
            $item['entity_id']   = $userId;
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
            unset($rules['entity_type'], $rules['entity_id'],
                $rules['module_id']);
            return $rules;
        });
        $this->setCallback('itemAfterValidation', function ($item) use ($userId, $id) {
            $this->model->where($this->getDirectRelationCriteria($userId));
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
        $this->setCallback('after', function ($response) use ($userId) {
            if ($response['affected'] > 0) {
                $this->purgeCache($userId);
            }
        });
        return $this->baseUpdate($id);
    }

    public function delete($userId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) use($userId) {
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
        $this->setCallback('itemAfterValidation', function ($item) use($userId) {
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
