<?php

namespace App\Controllers\V1\Branches\Accounting\Receipts;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Accounting\Payment;

/**
 * Medios de pago recibidos asociados a recibos de caja (acc_receipt_payment).
 *
 * Definición por sucursal y recibo (v1/branches/{branch_id}/accounting/receipts/{receipt_id}/payments).
 *
 * - Crear: añade un pago multimoneda a un recibo en borrador ('0').
 *   Delega la normalización, validaciones de saldo a favor y cálculo de tasas/USD a Payment.
 * - Actualizar: recalcula tasa y amount_usd mediante Payment si cambia origen, fecha o monto.
 * - Leer: consulta registros de pago mediante ReceiptPaymentSchema filtrados por receipt_id.
 * - Eliminar/Restaurar: soft-delete con scoping de receipt_id mientras el recibo esté en borrador.
 */
class Payments extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Accounting\ReceiptPaymentModel';
    protected $schemaName = 'App\Schemas\Accounting\ReceiptPaymentSchema';
    private ?Payment $payment = null;

    public function create($branchId = null, $receiptId = null): Response {
        $receipt = $this->getAncestorRelation($branchId, $receiptId, true);
        if ($receipt instanceof Response) {
            return $receipt;
        }

        $this->setCallback('setRules', function (array $rules): array {
            unset($rules['exchange_rate'], $rules['amount_usd']);
            return $rules;
        });

        $this->setCallback('itemSetValues', function (array $item) use ($receiptId): array {
            return $this->getPayment()->normalizeInput($item, (int) $receiptId);
        });

        $this->setCallback('itemAfterValidation', function (array $item) use ($receiptId, $receipt) {
            return $this->getPayment()->prepare($item, (int) $receiptId, $receipt);
        });

        return $this->baseCreate();
    }

    public function index($branchId = null, $receiptId = null): Response {
        return $this->_read($branchId, $receiptId);
    }

    public function show($branchId = null, $receiptId = null, $id = null): Response {
        return $this->_read($branchId, $receiptId, $id);
    }

    public function update($branchId = null, $receiptId = null, $id = null): Response {
        $receipt = $this->getAncestorRelation($branchId, $receiptId, true);
        if ($receipt instanceof Response) {
            return $receipt;
        }

        $this->setCallback('setRules', function (array $rules): array {
            unset($rules['exchange_rate'], $rules['amount_usd'], $rules['receipt_id']);
            return $rules;
        });

        $this->setCallback('itemSetValues', function (array $item) use ($receiptId): array {
            return $this->getPayment()->normalizeInput($item, (int) $receiptId);
        });

        $this->setCallback('itemAfterValidation', function (array $item, array $rawItem) use ($receiptId, $id, $receipt) {
            $this->model->where($this->getDirectRelationCriteria($receiptId));
            $currentId = $item['id'] ?? $rawItem['id'] ?? $id;
            return $this->getPayment()->prepare($item, (int) $receiptId, $receipt, $currentId ? (int) $currentId : null);
        });

        return $this->baseUpdate($id);
    }

    public function delete($branchId = null, $receiptId = null, $id = null): Response {
        $receipt = $this->getAncestorRelation($branchId, $receiptId, true);
        if ($receipt instanceof Response) {
            return $receipt;
        }

        $this->setCallback('itemAfterValidation', function () use ($receiptId): void {
            $this->model->where($this->getDirectRelationCriteria($receiptId));
        });

        return $this->baseDelete($id);
    }

    public function restore($branchId = null, $receiptId = null, $id = null): Response {
        $receipt = $this->getAncestorRelation($branchId, $receiptId, true);
        if ($receipt instanceof Response) {
            return $receipt;
        }

        $this->setCallback('itemAfterValidation', function () use ($receiptId): void {
            $this->model->where($this->getDirectRelationCriteria($receiptId));
        });

        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($branchId, $receiptId, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        $receipt = $this->getAncestorRelation($branchId, $receiptId, false);
        if ($receipt instanceof Response) {
            return $receipt;
        }

        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($receiptId, true));

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria(int|string $receiptId, bool $isRead = false): array {
        if ($isRead) {
            return [
                ['receipt_id', $receiptId],
            ];
        }
        return [
            'receipt_id' => $receiptId,
        ];
    }

    private function getAncestorRelation(int|string $branchId, int|string $receiptId, bool $requireDraft = false): array|Response {
        $builder = $this->model->db->table('acc_receipt as r')
            ->select('r.id, r.branch_id, r.client_id, r.status')
            ->join('glb_branch as b', 'r.branch_id = b.id', 'inner')
            ->where('r.id', $receiptId)
            ->where('r.branch_id', $branchId)
            ->where('r.deleted_at IS NULL')
            ->where('b.deleted_at IS NULL')
            ->where('b.is_disabled', '0');

        $receipt = $builder->limit(1)->get()->getRowArray();

        if (!$receipt) {
            return $this->fail(lang('Receipt.receipt_not_found'), 404, 'RECEIPT_NOT_FOUND');
        }

        if ($requireDraft && $receipt['status'] !== '0') {
            return $this->fail(lang('Receipt.receipt_not_draft'), 409, 'RECEIPT_NOT_DRAFT');
        }

        return $receipt;
    }

    private function getReceipt(mixed $branchId, mixed $receiptId, bool $requireDraft = false): array|Response {
        return $this->getAncestorRelation($branchId, $receiptId, $requireDraft);
    }

    private function getPayment(): Payment {
        if ($this->payment === null) {
            $this->payment = new Payment($this->model->db);
        }
        return $this->payment;
    }
}
