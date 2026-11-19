<?php

namespace App\Controllers\V1\Branches\Accounting\Receipts;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;

/**
 * Renglones y obligaciones vinculadas a recibos de caja (acc_receipt_item).
 *
 * Definición por sucursal y recibo (v1/branches/{branch_id}/accounting/receipts/{receipt_id}/items).
 *
 * - Crear: vincula una cuenta por cobrar (receivable_id) a un recibo en borrador ('0').
 *   Inyecta receipt_id, valida pertenencia al cliente del recibo y la sucursal,
 *   y verifica clave compuesta [receipt_id, receivable_id].
 * - Actualizar: permite modificar notas mientras el recibo esté en borrador.
 * - Leer: consulta renglones vinculados mediante ReceiptItemSchema filtrados por receipt_id.
 * - Eliminar/Restaurar: soft-delete con scoping de receipt_id mientras el recibo esté en borrador.
 */
class Items extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Accounting\ReceiptItemModel';
    protected $schemaName = 'App\Schemas\Accounting\ReceiptItemSchema';
    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function create($branchId = null, $receiptId = null): Response {
        $receipt = $this->getAncestorRelation($branchId, $receiptId, true);
        if ($receipt instanceof Response) {
            return $receipt;
        }

        $this->setCallback('setRules', function (array $rules): array {
            unset($rules['amount'], $rules['type']);
            return $rules;
        });

        $this->setCallback('itemSetValues', function (array $item) use ($receiptId): array {
            $item['receipt_id'] = (int) $receiptId;
            return $item;
        });

        $this->setCallback('itemAfterValidation', function (array $item) use ($branchId, $receipt) {
            $receivableId = (int) ($item['receivable_id'] ?? 0);
            $receivable = $this->model->db->table('acc_receivable')
                ->where('id', $receivableId)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRowArray();

            if (!$receivable || (int) $receivable['branch_id'] !== (int) $branchId || (int) $receivable['client_id'] !== (int) $receipt['client_id']) {
                return [
                    'status'   => 400,
                    'error'    => 'RECEIVABLE_CLIENT_MISMATCH',
                    'messages' => ['receivable_id' => lang('Receipt.receivable_client_mismatch')],
                ];
            }

            if (!in_array($receivable['status'], ['0', '1'], true)) {
                return [
                    'status'   => 400,
                    'error'    => 'RECEIVABLE_NOT_PAYABLE',
                    'messages' => ['receivable_id' => lang('Receipt.receivable_not_payable', [$receivableId])],
                ];
            }

            $res = $this->validationOfCompositeKeys($item);
            if ($res !== true) {
                return $res;
            }

            $item['receipt_id'] = (int) $receipt['id'];
            $item['amount']     = null;
            return $item;
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
            unset($rules['amount'], $rules['type']);
            return $rules;
        });

        $this->setCallback('itemAfterValidation', function (array $item, array $rawItem) use ($branchId, $receipt, $receiptId, $id) {
            $this->model->where($this->getDirectRelationCriteria($receiptId));

            if (isset($item['receivable_id'])) {
                $receivableId = (int) $item['receivable_id'];
                $receivable = $this->model->db->table('acc_receivable')
                    ->where('id', $receivableId)
                    ->where('deleted_at IS NULL')
                    ->get()
                    ->getRowArray();

                if (!$receivable || (int) $receivable['branch_id'] !== (int) $branchId || (int) $receivable['client_id'] !== (int) $receipt['client_id']) {
                    return [
                        'status'   => 400,
                        'error'    => 'RECEIVABLE_CLIENT_MISMATCH',
                        'messages' => ['receivable_id' => lang('Receipt.receivable_client_mismatch')],
                    ];
                }

                if (!in_array($receivable['status'], ['0', '1'], true)) {
                    return [
                        'status'   => 400,
                        'error'    => 'RECEIVABLE_NOT_PAYABLE',
                        'messages' => ['receivable_id' => lang('Receipt.receivable_not_payable', [$receivableId])],
                    ];
                }

                $item['receipt_id'] = (int) $receipt['id'];
                $item['id']         = $item['id'] ?? $id;
                $res = $this->validationOfCompositeKeys($item);
                if ($res !== true) {
                    return $res;
                }
            }

            $item['amount'] = null;
            return $item;
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

    private function validationOfCompositeKeys(array $item): bool|array {
        if ($this->compoundKeysValidator === null) {
            $this->compoundKeysValidator = new CompoundKeysValidator();
        }

        if (!$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['receipt_id', 'receivable_id'],
            pkField: $this->model->primaryKey
        )) {
            return [
                'status'   => 400,
                'error'    => 'FAIL_VALIDATION',
                'messages' => ['receivable_id' => $this->compoundKeysValidator->getError()],
            ];
        }

        return true;
    }
}
