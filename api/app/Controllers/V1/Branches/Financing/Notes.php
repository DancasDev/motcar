<?php

namespace App\Controllers\V1\Branches\Financing;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Notas operativas y bitácora CRM de un financiamiento (`glb_note`) en `v1/branches/{branchId}/financing/{financingId}/notes`.
 *
 * Scoping polimórfico por `origin_type=1` y `financing_id`, resolución de autor actual y validación jerárquica con `getAncestorRelation`.
 */
class Notes extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\NoteModel';
    protected $schemaName = 'App\Schemas\Global\NoteSchema';

    public function create($branchId = null, $financingId = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $financingId);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        $this->setCallback('itemSetValues', function (array $item) use ($financingId): array {
            $item['origin_type'] = '1';
            $item['origin_id']   = $financingId;
            $item['author_id']   = service('context')->get('auth:userId');
            return $item;
        });

        return $this->baseCreate();
    }

    public function index($branchId = null, $financingId = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $financingId, false);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        return $this->_read($financingId);
    }

    public function show($branchId = null, $financingId = null, $id = null): Response {
        if (!is_numeric($id)) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        $ancestor = $this->getAncestorRelation($branchId, $financingId, false);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        return $this->_read($financingId, $id);
    }

    public function update($branchId = null, $financingId = null, $id = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $financingId);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        $this->setCallback('itemSetValues', function (array $item): array {
            unset($item['origin_type'], $item['origin_id'], $item['author_id']);
            return $item;
        });

        $this->setCallback('itemAfterValidation', function () use ($financingId): void {
            $this->model->where($this->getDirectRelationCriteria($financingId));
        });

        return $this->baseUpdate($id);
    }

    public function delete($branchId = null, $financingId = null, $id = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $financingId);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        $this->setCallback('itemAfterValidation', function () use ($financingId): void {
            $this->model->where($this->getDirectRelationCriteria($financingId));
        });

        return $this->baseDelete($id);
    }

    public function restore($branchId = null, $financingId = null, $id = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $financingId);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        $this->setCallback('itemAfterValidation', function () use ($financingId): void {
            $this->model->where($this->getDirectRelationCriteria($financingId));
        });

        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($financingId, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($financingId, true));

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria(int|string $financingId, bool $isRead = false): array {
        if ($isRead) {
            return [
                ['origin_type', '1'],
                ['origin_id', $financingId],
            ];
        }
        return [
            'origin_type' => '1',
            'origin_id'   => $financingId,
        ];
    }

    private function getAncestorRelation(int|string $branchId, int|string $financingId, bool $addIsDisabled = true): array|Response {
        $builder = $this->model->db->table('fnc_financing as f')
            ->select('f.id, f.branch_id')
            ->join('glb_branch as b', 'f.branch_id = b.id', 'inner')
            ->where('f.id', $financingId)
            ->where('f.branch_id', $branchId)
            ->where('f.deleted_at IS NULL')
            ->where('b.deleted_at IS NULL');

        if ($addIsDisabled) {
            $builder->where('f.is_disabled', '0')
                    ->where('b.is_disabled', '0');
        }

        $ancestor = $builder->limit(1)->get()->getRowArray();

        if (!$ancestor) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        return $ancestor;
    }
}
