<?php

namespace App\Controllers\V1\Access\Users;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;
use DancasDev\GAC\Restrictions\Restrictions as GacRestrictions;
use DancasDev\GAC\Utils as GacUtils;

/**
 * Gestión de restricciones de acceso de usuario (`gac_restriction`) en `v1/access/users/{userId}/restrictions`.
 *
 * Scoping automático por `entity_type=1` y `userId`, validación de estructura de reglas (horario, IP, dominio)
 * contra el motor GAC, y purga de caché GAC (`purgeCache`).
 */
class Restrictions extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Gac\RestrictionModel';
    protected $schemaName = 'App\Schemas\Gac\RestrictionSchema';
    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create($userId = null): Response {
        $this->setCallback('itemSetValues', function ($item) use ($userId) {
            $item['entity_type'] = '1';
            $item['entity_id']   = $userId;
            $item['scope_path'] ??= '*';
            return $item;
        });
        $this->setCallback('itemSetRules', function (array $rules, array $item) {
            $rulesList = $this->model::RULES_BY_TYPE[(string) ($item['type'] ?? '')] ?? null;
            if ($rulesList !== null) {
                $rules['rule'] .= '|in_list[' . $rulesList . ']';
            }
            return $rules;
        });
        $this->setCallback('itemAfterValidation', function ($item) {
            if (!GacUtils::scopeValidate($item['scope_path'] ?? null)) {
                return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['scope_path' => lang('Validation.scopeInvalid')]];
            }
            $result = $this->validationOfCompositeKeys($item);
            if ($result !== true) {
                return $result;
            }
            
            $result = $this->validationOfRestrictionConfig($item);
            if ($result !== true) return $result;
        });
        $this->setCallback('after', function ($response) use ($userId) {
            if ($response['affected'] > 0) $this->purgeCache($userId);
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
                $rules['type'], $rules['rule']);
            return $rules;
        });
        $this->setCallback('itemAfterValidation', function ($item) use ($userId) {
            if (isset($item['config'])) {
                $result = $this->model->select('type,rule')->find($item['id']);
                $merged = array_merge($item, $result);

                $result = $this->validationOfRestrictionConfig($merged);
                if ($result !== true) return $result;

                $item['config'] = $merged['config'];
            }

            if (array_key_exists('scope_path', $item)) {
                if (!GacUtils::scopeValidate($item['scope_path'])) {
                    return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['scope_path' => lang('Validation.scopeInvalid')]];
                }
                $result = $this->validationOfCompositeKeys($item);
                if ($result !== true) return $result;
            }

            $this->model->where($this->getDirectRelationCriteria($userId));
        });
        $this->setCallback('after', function ($response) use ($userId) {
            if ($response['affected'] > 0) $this->purgeCache($userId);
        });
        return $this->baseUpdate($id);
    }

    public function delete($userId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) use ($userId) {
            $this->model->where($this->getDirectRelationCriteria($userId));
        });
        $this->setCallback('after', function ($response) use ($userId) {
            if ($response['affected'] > 0) $this->purgeCache($userId);
        });
        return $this->baseDelete($id);
    }

    public function restore($userId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) use ($userId) {
            $this->model->where($this->getDirectRelationCriteria($userId));
        });
        $this->setCallback('after', function ($response) use ($userId) {
            if ($response['affected'] > 0) $this->purgeCache($userId);
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
            fields: ['entity_type', 'entity_id', 'scope_path', 'type', 'rule'],
            pkField: $this->model->primaryKey
        )) {
            return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['rule' => $this->compoundKeysValidator->getError()]];
        }

        return true;
    }

    private function validationOfRestrictionConfig(array &$item): bool|array {
        $sanitized = GacRestrictions::validateStructure(
            $item['type'] ?? '',
            $item['rule'] ?? '',
            $item['config'] ?? []
        );

        if ($sanitized === false) {
            return ['status' => 400, 'error' => 'FAIL_VALIDATION',
                    'messages' => ['config' => lang('Restrictions.invalid_structure', [$item['type'] ?? '?', $item['rule'] ?? '?'])]];
        }

        $item['config'] = $sanitized;

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
