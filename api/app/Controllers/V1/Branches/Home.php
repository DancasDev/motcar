<?php

namespace App\Controllers\V1\Branches;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;
use App\Models\Global\BranchModel;

/**
 * Gestión de sucursales (`glb_branch`) en `v1/branches`.
 *
 * Estructura de árbol jerárquico, validación anti-ciclos, unicidad parent_id + name,
 * endpoint dedicado de árbol `tree` y caché del mapa de sucursales (`purgeCache`).
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\BranchModel';
    protected $schemaName = 'App\Schemas\Global\BranchSchema';
    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create(): Response {
        $this->setCallback('setRules', function ($rules) {
            $rules['parent_id'] = 'permit_empty|is_natural_no_zero';
            return $rules;
        });
        $this->setCallback('itemAfterValidation', function ($item) {
            $result = $this->validateBranchTree($item);
            if ($result !== true) {
                return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => $result];
            }
            $item['parent_id'] = $item['parent_id'] ?? null;
            $result = $this->validationOfCompositeKeys($item);
            if ($result !== true) {
                return $result;
            }
        });
        $this->setCallback('after', function ($response) {
            if (($response['affected'] ?? 0) > 0) {
                $this->purgeCache();
            }
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
        $this->setCallback('setRules', function ($rules) {
            $rules['parent_id'] = 'permit_empty|is_natural_no_zero';
            return $rules;
        });
        $this->setCallback('itemAfterValidation', function ($item) {
            if (array_key_exists('parent_id', $item)) {
                $result = $this->validateBranchTree($item, (int) $item['id']);
                if ($result !== true) {
                    return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => $result];
                }
            }
            if (array_key_exists('parent_id', $item) || array_key_exists('name', $item)) {
                if (!array_key_exists('parent_id', $item)) {
                    $current = $this->model->select('parent_id')->find($item['id']);
                    $item['parent_id'] = $current['parent_id'] ?? null;
                }
                $result = $this->validationOfCompositeKeys($item);
                if ($result !== true) {
                    return $result;
                }
            }
        });
        $this->setCallback('after', function ($response) {
            if (($response['affected'] ?? 0) > 0) {
                $this->purgeCache();
            }
        });
        return $this->baseUpdate($id);
    }

    public function delete($id = null): Response {
        $this->setCallback('itemAfterValidation', function ($item) {
            if (service('context')->get('request:inTrash', false)) {
                $children = $this->model->where('parent_id', $item['id'])
                    ->where('deleted_at', null)
                    ->limit(1)
                    ->countAllResults();
                if ($children > 0) {
                    return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['parent_id' => lang('Branches.has_children')]];
                }
            }
        });
        $this->setCallback('after', function ($response) {
            if (($response['affected'] ?? 0) > 0) {
                $this->purgeCache();
            }
        });
        return $this->baseDelete($id);
    }

    public function restore($id = null): Response {
        $this->setCallback('after', function ($response) {
            if (($response['affected'] ?? 0) > 0) {
                $this->purgeCache();
            }
        });
        return $this->baseRestore($id);
    }

    /**
     * Árbol completo de sucursales (mapa cacheado por BranchModel).
     */
    public function tree(): Response {
        $data = (new BranchModel())->getMapByCache();
        return $this->respond(['status' => 200, 'data' => $data, 'limit' => null, 'affected' => count($data)], 200);
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

    /**
     * Valida las restricciones del árbol de sucursales.
     *
     * - `parent_id` no puede ser el propio nodo ni un descendiente (ciclo).
     * - `parent_id` debe existir, estar activo y no eliminado.
     *
     * La unicidad de `name` por rama (parent_id) la valida
     * `validationOfCompositeKeys` (clave compuesta).
     */
    private function validateBranchTree(array $item, ?int $branchId = null): true|array {
        $model = new BranchModel();

        $parentId = !empty($item['parent_id']) ? (int) $item['parent_id'] : null;

        if ($parentId !== null) {
            if ($branchId !== null && $parentId === $branchId) {
                return ['parent_id' => lang('Branches.parent_self')];
            }
            if ($branchId !== null && $model->isInAncestors($parentId, $branchId)) {
                return ['parent_id' => lang('Branches.parent_cycle')];
            }

            $parent = $model->select('id')
                ->where('id', $parentId)
                ->where('is_disabled', '0')
                ->where('deleted_at', null)
                ->limit(1)
                ->get()->getRow();
            if (!$parent) {
                return ['parent_id' => lang('Branches.parent_invalid')];
            }
        }

        return true;
    }

    private function validationOfCompositeKeys(array $item): bool|array {
        if ($this->compoundKeysValidator === null) {
            $this->compoundKeysValidator = new CompoundKeysValidator();
        }

        if (!$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['parent_id', 'name'],
            pkField: $this->model->primaryKey
        )) {
            return ['status' => 400, 'error' => 'FAIL_VALIDATION', 'messages' => ['name' => $this->compoundKeysValidator->getError()]];
        }

        return true;
    }

    private function purgeCache(): void {
        (new BranchModel())->removeMapByCache();
    }
}
