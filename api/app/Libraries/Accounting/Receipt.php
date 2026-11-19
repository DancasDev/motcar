<?php

namespace App\Libraries\Accounting;

use Config\Database;
use CodeIgniter\Database\BaseConnection;
use App\Libraries\Finance\SanGroup;

/**
 * Componente del dominio contable para el ciclo de vida, imputación FIFO y comprobantes de recibos (acc_receipt).
 *
 * Responsabilidades:
 * - Algoritmo de prelación y cascada FIFO de cuentas por cobrar.
 * - Transición de estados (Borrador '0' -> Confirmado '1' -> Anulado '2').
 * - Imputación atómica de pagos y generación de saldo a favor por excedente.
 * - Reversión contable atómica al anular comprobantes.
 * - Generación del detalle consolidado del recibo.
 */
class Receipt {
    private BaseConnection $db;
    private Payment $payment;
    private ?SanGroup $sanGroup = null;

    public function __construct(?BaseConnection $db = null, ?Payment $payment = null, ?SanGroup $sanGroup = null) {
        $this->db       = $db ?? Database::connect();
        $this->payment  = $payment ?? new Payment($this->db);
        $this->sanGroup = $sanGroup;
    }

    /**
     * Ordena las cuentas por cobrar bajo la prelación de cascada FIFO:
     * 1. Prioridad 1: Cargos no capitalizables (gastos administrativos y recargos por mora).
     * 2. Prioridad 2: Cuotas vencidas (due_date < hoy) ordenadas cronológicamente (due_date ASC).
     * 3. Prioridad 3: Cuotas corrientes o futuras (due_date >= hoy) ordenadas por due_date ASC.
     */
    public function orderReceivablesForCascade(array $receivables): array {
        $today = date('Y-m-d');

        usort($receivables, function (array $a, array $b) use ($today): int {
            $prioA = $this->getReceivablePriority($a, $today);
            $prioB = $this->getReceivablePriority($b, $today);

            if ($prioA !== $prioB) {
                return $prioA <=> $prioB;
            }

            $cmpDate = strcmp($a['due_date'], $b['due_date']);
            if ($cmpDate !== 0) {
                return $cmpDate;
            }

            return ((int) $a['id']) <=> ((int) $b['id']);
        });

        return $receivables;
    }

    /**
     * Distribuye un importe USD disponible entre las cuentas por cobrar en cascada.
     */
    public function distributeAmount(float $totalUsd, array $receivables): array {
        $allocations = [];
        $remainingUsd = round($totalUsd, 2);

        foreach ($receivables as $r) {
            if ($remainingUsd <= 0.0) {
                break;
            }

            $totalAmount = (float) $r['total_amount'];
            $paidAmount  = (float) $r['paid_amount'];
            $pending     = round($totalAmount - $paidAmount, 2);

            if ($pending <= 0.0) {
                continue;
            }

            if ($remainingUsd >= $pending) {
                $allocatedAmount = $pending;
                $newPaidAmount   = $totalAmount;
                $newStatus       = '2'; // Pagado
                $remainingUsd    = round($remainingUsd - $pending, 2);
            } else {
                $allocatedAmount = $remainingUsd;
                $newPaidAmount   = round($paidAmount + $remainingUsd, 2);
                $newStatus       = '1'; // Parcial
                $remainingUsd    = 0.0;
            }

            // type: 0=no definido aun, 1=pago completo, 2=abono, 3=restante
            if ($paidAmount <= 0.001) {
                $type = ($allocatedAmount >= ($totalAmount - 0.001)) ? '1' : '2';
            } else {
                $type = ($allocatedAmount >= ($pending - 0.001)) ? '3' : '2';
            }

            $allocations[] = [
                'receivable_id'    => (int) $r['id'],
                'allocated_amount' => $allocatedAmount,
                'new_paid_amount'  => $newPaidAmount,
                'new_status'       => $newStatus,
                'type'             => $type,
            ];
        }

        return [
            'allocations'   => $allocations,
            'allocated_usd' => round($totalUsd - $remainingUsd, 2),
            'unallocated'   => $remainingUsd,
        ];
    }

    /**
     * Transiciona el estado del recibo:
     * - '1': Confirmar con imputación FIFO y saldo a favor si hay remanente.
     * - '2': Anular con reversión contable y cancelación de crédito generado.
     */
    public function changeStatus(int $branchId, int $receiptId, string $newStatus, ?string $reason = null): array {
        if (!in_array($newStatus, ['1', '2'], true)) {
            return [
                'status'  => 400,
                'error'   => 'INVALID_STATUS',
                'message' => lang('Receipt.invalid_transition'),
            ];
        }

        $receipt = $this->db->table('acc_receipt')
            ->where('id', $receiptId)
            ->where('branch_id', $branchId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if (!$receipt) {
            return [
                'status'  => 404,
                'error'   => 'RECEIPT_NOT_FOUND',
                'message' => lang('Receipt.receipt_not_found'),
            ];
        }

        $currentStatus = $receipt['status'];

        if ($currentStatus === $newStatus) {
            return [
                'status'  => 409,
                'error'   => 'INVALID_TRANSITION',
                'message' => lang('Receipt.invalid_transition'),
            ];
        }

        if ($currentStatus === '2') {
            return [
                'status'  => 409,
                'error'   => 'RECEIPT_ALREADY_CLOSED',
                'message' => lang('Receipt.receipt_already_closed'),
            ];
        }

        if ($newStatus === '1') {
            if ($currentStatus !== '0') {
                return [
                    'status'  => 409,
                    'error'   => 'INVALID_TRANSITION',
                    'message' => lang('Receipt.invalid_transition'),
                ];
            }
            return $this->confirm($branchId, $receiptId, $receipt);
        }

        // newStatus === '2' (Anulado)
        if (empty($reason) || trim($reason) === '') {
            return [
                'status'  => 400,
                'error'   => 'CANCELLATION_REASON_REQUIRED',
                'message' => lang('Receipt.cancellation_reason_required'),
            ];
        }

        return $this->void($branchId, $receiptId, $receipt, trim($reason));
    }



    // ---------------------------------------------------------------
    //  Procesos Internos de Ciclo de Vida (Transaccionales, sin N+1)
    // ---------------------------------------------------------------

    /**
     * Confirma un recibo en borrador: valida balance, corre cascada FIFO, amortiza deudas
     * y genera saldo a favor en caso de sobrepago.
     */
    private function confirm(int $branchId, int $receiptId, array $receipt): array {
        $clientId = (int) $receipt['client_id'];

        // 1. Obtener pagos activos
        $payments = $this->db->table('acc_receipt_payment')
            ->where('receipt_id', $receiptId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        if (empty($payments)) {
            return [
                'status'  => 400,
                'error'   => 'PAYMENTS_EMPTY',
                'message' => lang('Receipt.payments_empty'),
            ];
        }

        // 2. Obtener ítems activos
        $items = $this->db->table('acc_receipt_item')
            ->where('receipt_id', $receiptId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        if (empty($items)) {
            return [
                'status'  => 400,
                'error'   => 'ITEMS_EMPTY',
                'message' => lang('Receipt.items_empty'),
            ];
        }

        $totalReceivedUsd = $this->payment->calculateUsdTotal($payments);
        if ($totalReceivedUsd <= 0.0) {
            return [
                'status'  => 400,
                'error'   => 'INVALID_PAYMENT_AMOUNT',
                'message' => 'El importe total de pago debe ser mayor a 0',
            ];
        }

        $receivableIds = array_map(fn($it) => (int) $it['receivable_id'], $items);

        // 3. Consultar obligaciones activas en un solo query (Zero N+1)
        $receivableRecords = $this->db->table('acc_receivable')
            ->select('acc_receivable.*, acc_receivable_concept.code as concept_code')
            ->join('acc_receivable_concept', 'acc_receivable.receivable_concept_id = acc_receivable_concept.id', 'left')
            ->where('acc_receivable.branch_id', $branchId)
            ->where('acc_receivable.client_id', $clientId)
            ->whereIn('acc_receivable.id', $receivableIds)
            ->whereIn('acc_receivable.status', ['0', '1'])
            ->where('acc_receivable.deleted_at IS NULL')
            ->get()
            ->getResultArray();

        if (count($receivableRecords) !== count($receivableIds)) {
            $foundIds = array_column($receivableRecords, 'id');
            $missingIds = array_diff($receivableIds, $foundIds);
            return [
                'status'  => 409,
                'error'   => 'RECEIVABLE_NOT_PAYABLE',
                'message' => lang('Receipt.receivable_not_payable', [implode(', ', $missingIds)]),
            ];
        }

        // 4. Calcular cascada FIFO en memoria
        $orderedReceivables = $this->orderReceivablesForCascade($receivableRecords);
        $distribution       = $this->distributeAmount($totalReceivedUsd, $orderedReceivables);
        $allocations        = $distribution['allocations'];
        $unallocated        = $distribution['unallocated'];

        // 5. Verificar que ninguna obligación quede con $0.00 asignado
        $allocatedReceivableIds = array_column($allocations, 'receivable_id');
        $unreachedIds = [];
        foreach ($receivableIds as $rid) {
            if (!in_array($rid, $allocatedReceivableIds, true)) {
                $unreachedIds[] = $rid;
            }
        }
        foreach ($allocations as $al) {
            if ($al['allocated_amount'] <= 0.0) {
                $unreachedIds[] = $al['receivable_id'];
            }
        }

        if (!empty($unreachedIds)) {
            return [
                'status'  => 400,
                'error'   => 'RECEIPT_ITEM_UNREACHED',
                'message' => lang('Receipt.item_unreached', [implode(', ', array_unique($unreachedIds))]),
                'data'    => ['unreached_ids' => array_values(array_unique($unreachedIds))],
            ];
        }

        // 6. Validar deducciones de saldos a favor (origin_type = '1') SIN consultas N+1
        $creditIds = [];
        foreach ($payments as $p) {
            if (($p['origin_type'] ?? '0') === '1') {
                $creditIds[] = (int) $p['origin_id'];
            }
        }

        $creditsMap = [];
        if (!empty($creditIds)) {
            $creditRows = $this->db->table('acc_client_credit')
                ->whereIn('id', array_unique($creditIds))
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();
            $creditsMap = array_column($creditRows, null, 'id');
        }

        $creditsToDeduct = [];
        foreach ($payments as $p) {
            if (($p['origin_type'] ?? '0') === '1') {
                $creditId     = (int) $p['origin_id'];
                $creditAmount = (float) $p['amount'];

                if (!isset($creditsMap[$creditId])) {
                    return [
                        'status'  => 409,
                        'error'   => 'CREDIT_NOT_FOUND',
                        'message' => lang('Receipt.credit_not_found'),
                    ];
                }

                $creditRow = $creditsMap[$creditId];

                if ((int) $creditRow['client_id'] !== $clientId) {
                    return [
                        'status'  => 409,
                        'error'   => 'CREDIT_CLIENT_MISMATCH',
                        'message' => lang('Receipt.credit_client_mismatch'),
                    ];
                }

                if ($creditRow['status'] !== '0') {
                    return [
                        'status'  => 409,
                        'error'   => 'CREDIT_NOT_AVAILABLE',
                        'message' => lang('Receipt.credit_not_available'),
                    ];
                }

                $available = (float) $creditRow['available_amount'];
                $currentRemaining = isset($creditsToDeduct[$creditId])
                    ? $creditsToDeduct[$creditId]['new_available']
                    : $available;

                if ($creditAmount > $currentRemaining) {
                    return [
                        'status'  => 409,
                        'error'   => 'CREDIT_INSUFFICIENT_BALANCE',
                        'message' => lang('Receipt.credit_insufficient_balance', [$creditAmount, $currentRemaining]),
                    ];
                }

                $newAvailable = round($currentRemaining - $creditAmount, 2);
                $creditsToDeduct[$creditId] = [
                    'id'            => $creditId,
                    'new_available' => $newAvailable,
                    'new_status'    => ($newAvailable <= 0.0) ? '1' : '0',
                ];
            }
        }

        // 7. Preparar saldo a favor si existe remanente
        $creditData = null;
        $creditGenerated = null;
        if ($unallocated > 0.0) {
            $lastPayment     = end($payments);
            $lastPaymentRate = (float) ($lastPayment['exchange_rate'] ?? 1.0);
            $nominalSurplus  = round($unallocated * $lastPaymentRate, 2);

            $originType = $lastPayment['origin_type'] ?? '0';
            $originId   = (int) ($lastPayment['origin_id'] ?? 0);

            if ($originType === '1') {
                $sourceCredit     = $creditsMap[$originId] ?? null;
                $currencyId       = $sourceCredit ? (int) $sourceCredit['currency_id'] : 1;
                $paymentAccountId = $sourceCredit ? (int) $sourceCredit['payment_account_id'] : 1;
            } else {
                $accRow = $this->db->table('acc_payment_account')
                    ->select('currency_id')
                    ->where('id', $originId)
                    ->get()
                    ->getRowArray();
                $currencyId       = $accRow ? (int) $accRow['currency_id'] : 1;
                $paymentAccountId = $originId;
            }

            $creditData = [
                'branch_id'          => $branchId,
                'client_id'          => $clientId,
                'receipt_id'         => $receiptId,
                'payment_account_id' => $paymentAccountId,
                'currency_id'        => $currencyId,
                'amount'             => $nominalSurplus,
                'exchange_rate'      => $lastPaymentRate,
                'available_amount'   => $nominalSurplus,
                'status'             => '0',
                'created_at'         => date('Y-m-d H:i:s'),
            ];
        }

        // 8. PERSISTENCIA ATÓMICA: Transacción estricta sobre las escrituras
        $this->db->transStart();

        foreach ($creditsToDeduct as $deduct) {
            $this->db->table('acc_client_credit')
                ->where('id', $deduct['id'])
                ->update([
                    'available_amount' => $deduct['new_available'],
                    'status'           => $deduct['new_status'],
                    'updated_at'       => date('Y-m-d H:i:s'),
                ]);
        }

        if ($creditData !== null) {
            $this->db->table('acc_client_credit')->insert($creditData);
            $creditGenerated = [
                'id'            => (int) $this->db->insertID(),
                'currency_id'   => $creditData['currency_id'],
                'amount'        => $creditData['amount'],
                'exchange_rate' => $creditData['exchange_rate'],
            ];
        }

        $now = date('Y-m-d H:i:s');
        foreach ($allocations as $alloc) {
            $this->db->table('acc_receipt_item')
                ->where('receipt_id', $receiptId)
                ->where('receivable_id', $alloc['receivable_id'])
                ->where('deleted_at IS NULL')
                ->update([
                    'amount'     => $alloc['allocated_amount'],
                    'type'       => $alloc['type'],
                    'updated_at' => $now,
                ]);

            $this->db->table('acc_receivable')
                ->where('id', $alloc['receivable_id'])
                ->update([
                    'paid_amount' => $alloc['new_paid_amount'],
                    'status'      => $alloc['new_status'],
                    'updated_at'  => $now,
                ]);
        }

        $this->db->table('acc_receipt')
            ->where('id', $receiptId)
            ->update([
                'status'     => '1',
                'date'       => $now,
                'updated_at' => $now,
            ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'status'  => 500,
                'error'   => 'TRANSACTION_FAILED',
                'message' => 'Error al confirmar el recibo en la base de datos',
            ];
        }

        // Sincronizar morosidad del cliente e invalidar caché
        $this->getSanGroup()->syncOverdueForClient($branchId, $clientId);
        model(\App\Models\Accounting\ReceiptModel::class)->removeDetailByCache($branchId, $receiptId);

        return [
            'status' => 200,
            'data'   => [
                'receipt_id'       => $receiptId,
                'code'             => $receipt['code'],
                'status'           => '1',
                'total_usd'        => $totalReceivedUsd,
                'allocations'      => $allocations,
                'credit_generated' => $creditGenerated,
            ],
        ];
    }

    /**
     * Anula un recibo y reversa los efectos contables (Zero N+1).
     */
    private function void(int $branchId, int $receiptId, array $receipt, string $reason): array {
        $clientId     = (int) $receipt['client_id'];
        $wasConfirmed = ($receipt['status'] === '1');

        $creditsToVoid = [];
        $itemsToRevert = [];

        if ($wasConfirmed) {
            $credits = $this->db->table('acc_client_credit')
                ->where('receipt_id', $receiptId)
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();

            foreach ($credits as $cr) {
                if ((float) $cr['available_amount'] < (float) $cr['amount'] || $cr['status'] === '1') {
                    return [
                        'status'  => 409,
                        'error'   => 'CREDIT_ALREADY_CONSUMED',
                        'message' => lang('Receipt.credit_already_consumed'),
                    ];
                }
                $creditsToVoid[] = (int) $cr['id'];
            }

            $itemsToRevert = $this->db->table('acc_receipt_item')
                ->where('receipt_id', $receiptId)
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();
        }

        // 1. Obtener y preparar créditos a restaurar SIN N+1
        $creditsToRestore = [];
        $payments = $this->db->table('acc_receipt_payment')
            ->where('receipt_id', $receiptId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $creditIds = [];
        foreach ($payments as $p) {
            if (($p['origin_type'] ?? '0') === '1') {
                $creditIds[] = (int) $p['origin_id'];
            }
        }

        if (!empty($creditIds)) {
            $creditRows = $this->db->table('acc_client_credit')
                ->whereIn('id', array_unique($creditIds))
                ->get()
                ->getResultArray();
            $creditsMap = array_column($creditRows, null, 'id');

            foreach ($payments as $p) {
                if (($p['origin_type'] ?? '0') === '1') {
                    $creditId = (int) $p['origin_id'];
                    $amount   = (float) $p['amount'];
                    if (isset($creditsMap[$creditId])) {
                        $cr           = $creditsMap[$creditId];
                        $currentAvail = (float) $cr['available_amount'];
                        $accumAvail   = isset($creditsToRestore[$creditId])
                            ? $creditsToRestore[$creditId]['new_available']
                            : $currentAvail;
                        $newAvailable = round($accumAvail + $amount, 2);
                        $creditsToRestore[$creditId] = [
                            'id'            => $creditId,
                            'new_available' => $newAvailable,
                            'new_status'    => ($cr['status'] === '1') ? '0' : $cr['status'],
                        ];
                    }
                }
            }
        }

        // 2. Obtener y preparar obligaciones a revertir SIN N+1
        $reversions = [];
        if (!empty($itemsToRevert)) {
            $receivableIds = array_column($itemsToRevert, 'receivable_id');
            $receivableRows = $this->db->table('acc_receivable')
                ->whereIn('id', array_unique($receivableIds))
                ->get()
                ->getResultArray();
            $receivableMap = array_column($receivableRows, null, 'id');

            foreach ($itemsToRevert as $item) {
                $recId = (int) $item['receivable_id'];
                if (isset($receivableMap[$recId])) {
                    $rec         = $receivableMap[$recId];
                    $currentPaid = (float) $rec['paid_amount'];
                    $itemAmount  = (float) ($item['amount'] ?? 0.0);
                    $newPaid     = max(0.0, round($currentPaid - $itemAmount, 2));
                    $newStatus   = ($newPaid == 0.0) ? '0' : '1';
                    $reversions[] = [
                        'id'          => $rec['id'],
                        'paid_amount' => $newPaid,
                        'status'      => $newStatus,
                    ];
                }
            }
        }

        // 3. PERSISTENCIA ATÓMICA: Transacción estricta únicamente sobre las escrituras
        $this->db->transStart();

        $now = date('Y-m-d H:i:s');

        if (!empty($creditsToVoid)) {
            $this->db->table('acc_client_credit')
                ->whereIn('id', $creditsToVoid)
                ->update([
                    'status'           => '2',
                    'available_amount' => 0.00,
                    'updated_at'       => $now,
                ]);
        }

        foreach ($creditsToRestore as $restore) {
            $this->db->table('acc_client_credit')
                ->where('id', $restore['id'])
                ->update([
                    'available_amount' => $restore['new_available'],
                    'status'           => $restore['new_status'],
                    'updated_at'       => $now,
                ]);
        }

        foreach ($reversions as $rev) {
            $this->db->table('acc_receivable')
                ->where('id', $rev['id'])
                ->update([
                    'paid_amount' => $rev['paid_amount'],
                    'status'      => $rev['status'],
                    'updated_at'  => $now,
                ]);
        }

        $this->db->table('acc_receipt')
            ->where('id', $receiptId)
            ->update([
                'status'              => '2',
                'cancellation_reason' => $reason,
                'updated_at'          => $now,
            ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'status'  => 500,
                'error'   => 'TRANSACTION_FAILED',
                'message' => 'Error al anular el recibo en la base de datos',
            ];
        }

        // Sincronizar morosidad del cliente e invalidar caché
        $this->getSanGroup()->syncOverdueForClient($branchId, $clientId);
        model(\App\Models\Accounting\ReceiptModel::class)->removeDetailByCache($branchId, $receiptId);

        return [
            'status' => 200,
            'data'   => [
                'receipt_id'          => $receiptId,
                'status'              => '2',
                'cancellation_reason' => $reason,
            ],
        ];
    }

    private function getReceivablePriority(array $receivable, string $today): int {
        $conceptCode = $receivable['concept_code'] ?? '';
        $conceptId   = (int) ($receivable['receivable_concept_id'] ?? 0);

        if ($conceptId === 2 || $conceptId === 3 || in_array($conceptCode, ['admin_fee', 'late_fee'], true)) {
            return 1;
        }

        if ($receivable['due_date'] < $today) {
            return 2;
        }

        return 3;
    }

    private function getSanGroup(): SanGroup {
        if ($this->sanGroup === null) {
            $this->sanGroup = new SanGroup($this->db);
        }
        return $this->sanGroup;
    }
}
