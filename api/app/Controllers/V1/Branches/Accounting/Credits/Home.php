<?php

namespace App\Controllers\V1\Branches\Accounting\Credits;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;

/**
 * Saldos a favor y excedentes de pagos de clientes (acc_client_credit).
 *
 * Definición por sucursal (v1/branches/{branch_id}/accounting/credits).
 *
 * - Leer (index): listado de créditos de la sucursal mediante ClientCreditSchema con soporte DQB.
 * - Consultar (show): detalle de un saldo a favor asegurando pertenencia a la sucursal.
 * - Anular (void): anulación administrativa (status '2', available_amount = 0.00).
 *   Solo permitido si el crédito no ha sido consumido ni total ni parcialmente.
 *
 * Nota: Los saldos a favor se generan exclusivamente mediante la confirmación de recibos de cobro
 * (acc_receipt) con excedentes y se consumen mediante recibos de cobro con medios de pago
 * de origen saldo a favor (origin_type = '1'). No admite endpoints de creación (POST) ni eliminación física/arbitraria.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Accounting\ClientCreditModel';
    protected $schemaName = 'App\Schemas\Accounting\ClientCreditSchema';

    public function index($branchId = null): Response {
        return $this->_read($branchId);
    }

    public function show($branchId = null, $id = null): Response {
        return $this->_read($branchId, $id);
    }

    /**
     * Anulación administrativa de un saldo a favor no consumido.
     * PUT v1/branches/{branch_id}/accounting/credits/{id}/void
     */
    public function void($branchId = null, $id = null): Response {
        $credit = $this->model
            ->where($this->getDirectRelationCriteria($branchId))
            ->find($id);

        if (!$credit) {
            return $this->fail(lang('Credit.credit_not_found'), 404, 'CREDIT_NOT_FOUND');
        }

        if ($credit['status'] === '2') {
            return $this->fail(lang('Credit.credit_already_voided'), 409, 'CREDIT_ALREADY_VOIDED');
        }

        if ($credit['status'] === '1' || (float) $credit['available_amount'] < (float) $credit['amount']) {
            return $this->fail(lang('Credit.credit_already_consumed'), 409, 'CREDIT_ALREADY_CONSUMED');
        }

        $this->model->db->transStart();
        $this->model->update($id, [
            'status'           => '2',
            'available_amount' => 0.00,
        ]);
        $this->model->db->transComplete();

        return $this->response->setStatusCode(200)->setJSON([
            'status'  => 200,
            'message' => lang('Credit.credit_voided_successfully'),
            'data'    => $this->model->find($id),
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
