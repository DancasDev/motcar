<?php

namespace App\Controllers\V1\Branches\Financing\Groups;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Finance\SanGroup;

/**
 * Grupos de ahorro colectivo SAM por sucursal (fnc_san_group).
 *
 * Definición por sucursal (v1/branches/{branch_id}/financing/groups).
 *
 * - Crear: valida campos del modelo e inyecta branch_id de la sucursal.
 * - Actualizar: revalida campos y asegura pertenencia a la sucursal.
 * - Leer: registros planos mediante SanGroupSchema filtrados por branch_id.
 * - Eliminar/Restaurar: soft-delete con scoping de sucursal.
 * - generateReceivables: generación por lote de cuotas/deuda periódica.
 * - qualifiedParticipants: consulta de integrantes calificados para asamblea/adjudicación.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Finance\SanGroupModel';
    protected $schemaName = 'App\Schemas\Finance\SanGroupSchema';

    public function create($branchId = null): Response {
        $this->setCallback('itemSetValues', function (array $item) use ($branchId): array {
            $item['branch_id'] = $branchId;
            return $item;
        });

        return $this->baseCreate();
    }

    public function index($branchId = null): Response {
        return $this->_read($branchId);
    }

    public function show($branchId = null, $id = null): Response {
        return $this->_read($branchId, $id);
    }

    public function update($branchId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function () use ($branchId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId));
        });

        return $this->baseUpdate($id);
    }

    public function delete($branchId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function () use ($branchId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId));
        });

        return $this->baseDelete($id);
    }

    public function restore($branchId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function () use ($branchId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId));
        });

        return $this->baseRestore($id);
    }

    /**
     * Genera la cartera de cuentas por cobrar para el grupo SAM.
     * POST v1/branches/{branch_id}/financing/groups/{group_id}/generate-receivables
     */
    public function generateReceivables($branchId = null, $groupId = null): Response {
        $participantId = $this->request->getGet('participant_id') ?? $this->request->getPost('participant_id');
        $sanGroup = new SanGroup($this->model->db);
        $result   = $sanGroup->generateReceivables((int) $branchId, (int) $groupId, $participantId ? (int) $participantId : null);

        $status = $result['status'] ?? 200;
        return $this->response->setStatusCode($status)->setJSON($result);
    }

    /**
     * Consulta los participantes calificados para adjudicación (sin mora, activos, no adjudicados).
     * GET v1/branches/{branch_id}/financing/groups/{group_id}/qualified-participants
     */
    public function qualifiedParticipants($branchId = null, $groupId = null): Response {
        $sanGroup = new SanGroup($this->model->db);
        $data     = $sanGroup->getQualifiedParticipants((int) $branchId, (int) $groupId);

        return $this->response->setStatusCode(200)->setJSON([
            'status' => 200,
            'data'   => $data,
            'count'  => count($data),
        ]);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($branchId, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($branchId, true));

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria(int|string $branchId, bool $isRead = false): array {
        if ($isRead) {
            return [['branch_id', $branchId]];
        }
        return ['branch_id' => $branchId];
    }
}
