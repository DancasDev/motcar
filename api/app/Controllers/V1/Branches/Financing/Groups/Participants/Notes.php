<?php

namespace App\Controllers\V1\Branches\Financing\Groups\Participants;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Models\Finance\SanParticipantModel;

/**
 * Notas operativas y bitácora CRM de un participante SAM (`glb_note`) en `v1/branches/{branchId}/financing/groups/{groupId}/participants/{participantId}/notes`.
 *
 * Scoping polimórfico por `origin_type=0` y `participant_id`, resolución de autor actual y validación jerárquica con `getAncestorRelation`.
 */
class Notes extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Global\NoteModel';
    protected $schemaName = 'App\Schemas\Global\NoteSchema';

    public function create($branchId = null, $groupId = null, $participantId = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $groupId, $participantId);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        $this->setCallback('itemSetValues', function (array $item) use ($participantId): array {
            $item['origin_type'] = '0';
            $item['origin_id']   = $participantId;
            $item['author_id']   = service('context')->get('auth:userId');
            return $item;
        });

        return $this->baseCreate();
    }

    public function index($branchId = null, $groupId = null, $participantId = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $groupId, $participantId, false);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        return $this->_read($participantId);
    }

    public function show($branchId = null, $groupId = null, $participantId = null, $id = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $groupId, $participantId, false);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        return $this->_read($participantId, $id);
    }

    public function update($branchId = null, $groupId = null, $participantId = null, $id = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $groupId, $participantId);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        $this->setCallback('itemSetValues', function (array $item): array {
            unset($item['origin_type'], $item['origin_id'], $item['author_id']);
            return $item;
        });

        $this->setCallback('itemAfterValidation', function () use ($participantId): void {
            $this->model->where($this->getDirectRelationCriteria($participantId));
        });

        return $this->baseUpdate($id);
    }

    public function delete($branchId = null, $groupId = null, $participantId = null, $id = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $groupId, $participantId);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        $this->setCallback('itemAfterValidation', function () use ($participantId): void {
            $this->model->where($this->getDirectRelationCriteria($participantId));
        });

        return $this->baseDelete($id);
    }

    public function restore($branchId = null, $groupId = null, $participantId = null, $id = null): Response {
        $ancestor = $this->getAncestorRelation($branchId, $groupId, $participantId);
        if ($ancestor instanceof Response) {
            return $ancestor;
        }

        $this->setCallback('itemAfterValidation', function () use ($participantId): void {
            $this->model->where($this->getDirectRelationCriteria($participantId));
        });

        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($participantId, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($participantId, true));

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria(int|string $participantId, bool $isRead = false): array {
        if ($isRead) {
            return [
                ['origin_type', '0'],
                ['origin_id', $participantId],
            ];
        }
        return [
            'origin_type' => '0',
            'origin_id'   => $participantId,
        ];
    }

    private function getAncestorRelation($branchId, $groupId, $participantId, bool $addIsDisabled = true): array|Response {
        $builder = $this->model->db->table('fnc_san_participant as sp')
            ->select('sp.id, sp.branch_id, sp.san_group_id')
            ->join('fnc_san_group as sg', 'sp.san_group_id = sg.id', 'inner')
            ->join('glb_branch as b', 'sp.branch_id = b.id', 'inner')
            ->where('sp.id', $participantId)
            ->where('sp.branch_id', $branchId)
            ->where('sp.san_group_id', $groupId)
            ->where('sp.deleted_at IS NULL')
            ->where('sg.deleted_at IS NULL')
            ->where('b.deleted_at IS NULL');

        if ($addIsDisabled) {
            $builder->where('sg.is_disabled', '0')
                    ->where('b.is_disabled', '0');
        }

        $ancestor = $builder->limit(1)->get()->getRowArray();

        if (!$ancestor) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        return $ancestor;
    }

    private function getParticipant($branchId, $groupId, $participantId): array|Response {
        return $this->getAncestorRelation($branchId, $groupId, $participantId);
    }
}
