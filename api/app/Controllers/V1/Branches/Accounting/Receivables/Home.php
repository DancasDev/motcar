<?php

namespace App\Controllers\V1\Branches\Accounting\Receivables;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Finance\Financing;
use App\Libraries\Finance\SanGroup;

/**
 * Cuentas por cobrar de la sucursal (acc_receivable).
 *
 * Definición por sucursal (v1/branches/{branch_id}/accounting/receivables).
 *
 * - Crear: valida campos del modelo e inyecta branch_id de la sucursal.
 * - Actualizar: revalida campos asegurando pertenencia a la sucursal.
 * - Leer: registros planos mediante ReceivableSchema filtrados por branch_id.
 * - Eliminar/Restaurar: soft-delete con scoping de sucursal.
 * - overdueSync: sincronización masiva de banderas is_overdue en participantes.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Accounting\ReceivableModel';
    protected $schemaName = 'App\Schemas\Accounting\ReceivableSchema';

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
            $this->model->where($this->getDirectRelationCriteria($branchId))
                ->where('paid_amount', 0)
                ->where('status', '0');
        });

        return $this->baseUpdate($id);
    }

    public function delete($branchId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function () use ($branchId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId))
                ->where('paid_amount', 0)
                ->where('status', '0');
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
     * Sincroniza la morosidad de todos los participantes de la sucursal.
     * POST v1/branches/{branch_id}/accounting/receivables/overdue-sync
     */
    public function overdueSync($branchId = null): Response {
        $sanResult       = (new SanGroup($this->model->db))->syncOverdue($branchId);
        $financingResult = (new Financing($this->model->db))->syncOverdue($branchId);

        return $this->response->setStatusCode(200)->setJSON([
            'status' => 200,
            'data'   => [
                'processed' => ($sanResult['processed'] ?? 0) + ($financingResult['processed'] ?? 0),
                'updated'   => ($sanResult['updated'] ?? 0) + ($financingResult['updated'] ?? 0),
                'san'       => $sanResult,
                'financing' => $financingResult,
            ],
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

