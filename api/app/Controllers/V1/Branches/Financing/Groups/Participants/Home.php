<?php

namespace App\Controllers\V1\Branches\Financing\Groups\Participants;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;
use App\Libraries\Metadata\Metadata;
use App\Models\Finance\ContractTypeModel;

/**
 * Participantes y suscripciones de grupos SAM por sucursal (fnc_san_participant).
 *
 * Definición por sucursal y grupo (v1/branches/{branch_id}/financing/groups/{group_id}/participants).
 *
 * - Crear: valida campos, inyecta branch_id y san_group_id, valida unicidad de position y capacidad del grupo.
 * - Actualizar: revalida position, capacidad si se reactivan cupos, y pertenencia a sucursal y grupo.
 * - Leer: registros planos mediante SanParticipantSchema filtrados por branch_id y san_group_id.
 * - Eliminar/Restaurar: soft-delete con scoping y sincronización atómica de participant_count.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Finance\SanParticipantModel';
    protected $schemaName = 'App\Schemas\Finance\SanParticipantSchema';
    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create($branchId = null, $groupId = null): Response {
        $group = $this->getAncestorRelation($branchId, $groupId);
        if ($group instanceof Response) {
            return $group;
        }
        $metadata = $this->getBenefitMetadata($group);

        $runningCount    = (int) ($group['participant_count'] ?? 0);
        $maxParticipants = (int) ($group['participant_max'] ?? 0);

        $this->setCallback('itemSetValues', function (array $item) use ($branchId, $groupId): array {
            $item['branch_id']    = $branchId;
            $item['san_group_id'] = $groupId;
            return $item;
        });

        $this->setCallback('setRules', function (array $rules) use ($metadata): ?array {
            if ($metadata === null) {
                unset($rules['benefit_metadata']);
                return $rules;
            }
            return null;
        });

        $this->setCallback('itemAfterValidation', function (array $item, array $rawItem) use ($metadata, $group, &$runningCount, $maxParticipants) {
            $status = $item['status'] ?? '0';
            // Opción A: los estados '0' (En espera), '1' (Activo) y '3' (Culminado) ocupan cupo ('2' = Retirado no ocupa cupo)
            if ($status !== '2' && $group !== null) {
                if ($runningCount >= $maxParticipants) {
                    return [
                        'status'   => 400,
                        'error'    => 'GROUP_MAX_PARTICIPANTS_REACHED',
                        'messages' => ['san_group_id' => 'El grupo ha alcanzado el número máximo de participantes permitidos.'],
                    ];
                }
                $runningCount++;
            }

            if (!empty($item['position'])) {
                $res = $this->validationOfCompositeKeys($item);
                if ($res !== true) {
                    return $res;
                }
            }

            if ($metadata !== null && array_key_exists('benefit_metadata', $rawItem)) {
                return $metadata->process($item);
            }
        });

        $this->setCallback('after', function (array $response) use ($groupId): void {
            if (($response['affected'] ?? 0) > 0) {
                $this->syncParticipantCount((int) $groupId);
            }
        });

        return $this->baseCreate();
    }

    public function index($branchId = null, $groupId = null): Response {
        return $this->_read($branchId, $groupId);
    }

    public function show($branchId = null, $groupId = null, $id = null): Response {
        if (!is_numeric($id)) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }
        return $this->_read($branchId, $groupId, $id);
    }

    public function update($branchId = null, $groupId = null, $id = null): Response {
        $group = $this->getAncestorRelation($branchId, $groupId);
        if ($group instanceof Response) {
            return $group;
        }
        $metadata = $this->getBenefitMetadata($group);

        $this->setCallback('before', function (array $input) use ($branchId, $groupId, $id, $group) {
            if ($group === null) {
                return;
            }

            $isBatch = !is_numeric($id);
            $items   = $isBatch ? $input : [$input];

            $checkingItems = [];
            foreach ($items as $item) {
                if (isset($item['status']) && $item['status'] !== '2') {
                    $itemId = $isBatch ? ($item['id'] ?? null) : $id;
                    if ($itemId !== null) {
                        $checkingItems[] = (int) $itemId;
                    }
                }
            }

            if (empty($checkingItems)) {
                return;
            }

            // Una sola consulta agrupada para conocer el estado actual de los registros
            $previous = $this->model
                ->select('id, status')
                ->where('branch_id', $branchId)
                ->where('san_group_id', $groupId)
                ->whereIn('id', $checkingItems)
                ->findAll();

            $reactivatingCount = 0;
            foreach ($previous as $prev) {
                // Solo si antes estaba '2' (Retirado) y ahora pasa a '0', '1' o '3' ocupa un nuevo cupo
                if ($prev['status'] === '2') {
                    $reactivatingCount++;
                }
            }

            if ($reactivatingCount > 0 && ($group['participant_count'] + $reactivatingCount) > $group['participant_max']) {
                return $this->fail(
                    'El grupo ha alcanzado o superaría el número máximo de participantes permitidos.',
                    400,
                    'GROUP_MAX_PARTICIPANTS_REACHED'
                );
            }
        });

        $this->setCallback('setRules', function (array $rules) use ($metadata): ?array {
            if ($metadata === null) {
                unset($rules['benefit_metadata']);
                return $rules;
            }
            return null;
        });

        $this->setCallback('itemAfterValidation', function (array $item, array $rawItem) use ($branchId, $groupId, $id, $metadata) {
            $this->model->where($this->getDirectRelationCriteria($branchId, $groupId));

            if (isset($item['position'])) {
                $item['san_group_id'] = $groupId;
                $item['id']           = $item['id'] ?? $id;
                $res = $this->validationOfCompositeKeys($item);
                if ($res !== true) {
                    return $res;
                }
            }

            if ($metadata !== null && array_key_exists('benefit_metadata', $rawItem)) {
                return $metadata->process($item, $item['id'] ?? $id);
            }
        });

        $this->setCallback('after', function (array $response) use ($groupId): void {
            if (($response['affected'] ?? 0) > 0) {
                $this->syncParticipantCount((int) $groupId);
            }
        });

        return $this->baseUpdate($id);
    }

    public function delete($branchId = null, $groupId = null, $id = null): Response {
        $group = $this->getAncestorRelation($branchId, $groupId);
        if ($group instanceof Response) {
            return $group;
        }

        $this->setCallback('itemAfterValidation', function () use ($branchId, $groupId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId, $groupId));
        });

        $this->setCallback('after', function (array $response) use ($groupId): void {
            if (($response['affected'] ?? 0) > 0) {
                $this->syncParticipantCount((int) $groupId);
            }
        });

        return $this->baseDelete($id);
    }

    public function restore($branchId = null, $groupId = null, $id = null): Response {
        $group = $this->getAncestorRelation($branchId, $groupId);
        if ($group instanceof Response) {
            return $group;
        }

        $this->setCallback('before', function (array $input) use ($branchId, $groupId, $id, $group) {
            if ($group === null) {
                return;
            }

            $isBatch = !is_numeric($id);
            $ids     = $isBatch ? array_column($input, 'id') : [$id];
            $ids     = array_filter(array_map('intval', $ids));

            if (empty($ids)) {
                return;
            }

            // Una sola consulta agrupada para verificar estados de los eliminados
            $restoring = $this->model
                ->withDeleted()
                ->select('id, status')
                ->where('branch_id', $branchId)
                ->where('san_group_id', $groupId)
                ->whereIn('id', $ids)
                ->findAll();

            $countableCount = 0;
            foreach ($restoring as $row) {
                if ($row['status'] !== '2') {
                    $countableCount++;
                }
            }

            if ($countableCount > 0 && ($group['participant_count'] + $countableCount) > $group['participant_max']) {
                return $this->fail(
                    'El grupo ha alcanzado o superaría el número máximo de participantes permitidos.',
                    400,
                    'GROUP_MAX_PARTICIPANTS_REACHED'
                );
            }
        });

        $this->setCallback('itemAfterValidation', function () use ($branchId, $groupId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId, $groupId));
        });

        $this->setCallback('after', function (array $response) use ($groupId): void {
            if (($response['affected'] ?? 0) > 0) {
                $this->syncParticipantCount((int) $groupId);
            }
        });

        return $this->baseRestore($id);
    }

    /**
     * Genera o regenera la cartera de cuentas por cobrar para un participante específico.
     * POST v1/branches/{branch_id}/financing/groups/{group_id}/participants/{participant_id}/generate-receivables
     */
    public function generateReceivables($branchId = null, $groupId = null, $id = null): Response {
        $group = $this->getAncestorRelation($branchId, $groupId, false);
        if ($group instanceof Response) {
            return $group;
        }

        $sanGroup = new \App\Libraries\Finance\SanGroup($this->model->db);
        $result   = $sanGroup->generateReceivables((int) $branchId, (int) $groupId, (int) $id);

        $status = $result['status'] ?? 200;
        return $this->response->setStatusCode($status)->setJSON($result);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($branchId, $groupId, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        $group = $this->getAncestorRelation($branchId, $groupId, false);
        if ($group instanceof Response) {
            return $group;
        }

        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($branchId, $groupId, true));

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria(int|string $branchId, int|string $groupId, bool $isRead = false): array {
        if ($isRead) {
            return [
                ['branch_id', $branchId],
                ['san_group_id', $groupId],
            ];
        }
        return [
            'branch_id'    => $branchId,
            'san_group_id' => $groupId,
        ];
    }

    private function getAncestorRelation(int|string $branchId, int|string $groupId, bool $addIsDisabled = true): array|Response {
        $builder = $this->model->db->table('fnc_san_group as g')
            ->select('g.id, g.branch_id, g.participant_max, g.participant_count, g.contract_type_id')
            ->join('glb_branch as b', 'g.branch_id = b.id', 'inner')
            ->where('g.id', $groupId)
            ->where('g.branch_id', $branchId)
            ->where('g.deleted_at IS NULL')
            ->where('b.deleted_at IS NULL');

        if ($addIsDisabled) {
            $builder->where('g.is_disabled', '0')
                    ->where('b.is_disabled', '0');
        }

        $group = $builder->limit(1)->get()->getRowArray();

        if (!$group) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        return $group;
    }

    private function validationOfCompositeKeys(array $item): bool|array {
        if ($this->compoundKeysValidator === null) {
            $this->compoundKeysValidator = new CompoundKeysValidator();
        }

        if (!$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['san_group_id', 'position'],
            pkField: $this->model->primaryKey
        )) {
            return [
                'status'   => 400,
                'error'    => 'FAIL_VALIDATION',
                'messages' => ['position' => $this->compoundKeysValidator->getError()],
            ];
        }

        return true;
    }

    private function getGroup(int $groupId): ?array {
        return db_connect()->table('fnc_san_group')
            ->select('id, participant_max, participant_count, contract_type_id')
            ->where('id', $groupId)
            ->where('deleted_at IS NULL')
            ->limit(1)
            ->get()
            ->getRowArray();
    }

    private function getBenefitMetadata(?array $group): ?Metadata {
        $contractTypeId = $group['contract_type_id'] ?? null;
        if (!$contractTypeId) {
            return null;
        }

        $contractTypes = model(ContractTypeModel::class)->getByCache();
        $schemaId = $contractTypes[(int) $contractTypeId]['metadata_schema_id'] ?? null;

        return $schemaId !== null ? new Metadata((int) $schemaId) : null;
    }

    private function syncParticipantCount(int $groupId): void {
        db_connect()->query(
            "UPDATE fnc_san_group 
             SET participant_count = (
                 SELECT COUNT(*) 
                 FROM fnc_san_participant 
                 WHERE san_group_id = ? 
                   AND status IN ('0', '1', '3') 
                   AND deleted_at IS NULL
             ) 
             WHERE id = ?",
            [$groupId, $groupId]
        );
    }
}
