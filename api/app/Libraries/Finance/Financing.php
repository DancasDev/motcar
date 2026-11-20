<?php

namespace App\Libraries\Finance;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * Servicio de dominio para Financiamientos Individuales (`fnc_financing`).
 *
 * Responsabilidades:
 * - Generación de cartera de cuentas por cobrar (cuota inicial + cuotas periódicas con ajuste de residuo).
 * - Sincronización de morosidad (`is_overdue`) desde `acc_receivable` (`origin_type = '1'`).
 */
class Financing {
    private BaseConnection $db;
    private ?SanGroup $sanGroup = null;

    public function __construct(?BaseConnection $db = null, ?SanGroup $sanGroup = null) {
        $this->db       = $db ?? Database::connect();
        $this->sanGroup = $sanGroup;
    }

    /**
     * Genera o regenera la cartera de cuentas por cobrar (inicial y cuotas) para un financiamiento activo.
     */
    public function generateReceivables(int $branchId, int $financingId): array {
        $financing = $this->db->table('fnc_financing')
            ->where('id', $financingId)
            ->where('branch_id', $branchId)
            ->where('deleted_at IS NULL')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (!$financing) {
            return ['status' => 404, 'error' => 'FINANCING_NOT_FOUND', 'message' => 'Financiamiento no encontrado'];
        }

        if ($financing['status'] !== '1' || $financing['is_disabled'] !== '0') {
            return ['status' => 400, 'error' => 'FINANCING_NOT_ACTIVE', 'message' => 'El financiamiento no se encuentra activo'];
        }

        $conceptRows = $this->db->table('acc_receivable_concept')
            ->select('id, code, template')
            ->whereIn('code', ['initial_payment', 'base_installment'])
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $concepts         = array_column($conceptRows, null, 'code');
        $initialConcept   = $concepts['initial_payment'] ?? null;
        $baseConcept      = $concepts['base_installment'] ?? null;
        $initialConceptId = $initialConcept ? (int) $initialConcept['id'] : 6;
        $baseConceptId    = $baseConcept ? (int) $baseConcept['id'] : 1;
        $initialTemplate  = $initialConcept['template'] ?? 'Cuota Inicial - {code}';
        $baseTemplate     = $baseConcept['template'] ?? 'Cuota {installment_number} - {reference}';

        $freqRow = $this->db->table('fnc_frequency')
            ->select('code')
            ->where('id', $financing['frequency_id'])
            ->limit(1)
            ->get()
            ->getRowArray();
        $frequencyCode = $freqRow['code'] ?? 'monthly';

        $existingRows = $this->db->table('acc_receivable')
            ->select('id, receivable_concept_id, description, due_date, total_amount, paid_amount, status')
            ->where('origin_type', '1')
            ->where('origin_id', $financingId)
            ->whereIn('receivable_concept_id', [$initialConceptId, $baseConceptId])
            ->where('deleted_at IS NULL')
            ->orderBy('due_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $hasPaidInitial        = false;
        $keptInstallments      = [];
        $keptInstallmentsTotal = 0.0;
        $unpaidIdsToDelete     = [];

        foreach ($existingRows as $row) {
            $isPaidOrPartial = ((float) $row['paid_amount'] > 0.0 || $row['status'] === '2');
            $isUnpaidPending = ($row['status'] === '0' && (float) $row['paid_amount'] == 0.0);

            if ((int) $row['receivable_concept_id'] === $initialConceptId) {
                if ($isPaidOrPartial) {
                    $hasPaidInitial = true;
                } elseif ($isUnpaidPending) {
                    $unpaidIdsToDelete[] = (int) $row['id'];
                }
                continue;
            }

            if ($isPaidOrPartial) {
                $keptInstallments[]    = $row;
                $keptInstallmentsTotal = round($keptInstallmentsTotal + (float) $row['total_amount'], 2);
            } elseif ($isUnpaidPending) {
                $unpaidIdsToDelete[] = (int) $row['id'];
            }
        }

        $maxExisting = 0;
        $lastDueDate = null;

        foreach ($keptInstallments as $k => $row) {
            $num = preg_match('/(?:cuota|partida|#)\s*#?(\d+)/i', $row['description'], $matches)
                ? (int) $matches[1]
                : ($k + 1);

            if ($num > $maxExisting) {
                $maxExisting = $num;
            }
            if ($lastDueDate === null || strcmp($row['due_date'], $lastDueDate) > 0) {
                $lastDueDate = $row['due_date'];
            }
        }

        $clientId          = (int) $financing['client_id'];
        $code              = $financing['code'];
        $totalAmount       = (float) $financing['total_amount'];
        $initialAmount     = (float) $financing['initial_amount'];
        $installmentCount  = (int) $financing['installment_count'];
        $installmentAmount = (float) $financing['installment_amount'];
        $frequencyBaseDate = $financing['frequency_base_date'];
        $initialDueDate    = !empty($financing['initial_due_date']) ? $financing['initial_due_date'] : $frequencyBaseDate;
        $issueDate         = date('Y-m-d');
        $now               = date('Y-m-d H:i:s');
        $toInsert          = [];
        $initialGenerated  = 0;

        if (!$hasPaidInitial) {
            $initialDesc = str_replace(['{code}', '{reference}'], [$code, $code], $initialTemplate);
            $toInsert[]  = [
                'branch_id'             => $branchId,
                'client_id'             => $clientId,
                'receivable_concept_id' => $initialConceptId,
                'description'           => mb_substr($initialDesc, 0, 150),
                'origin_type'           => '1',
                'origin_id'             => $financingId,
                'total_amount'          => $initialAmount,
                'paid_amount'           => 0.00,
                'issue_date'            => $issueDate,
                'due_date'              => $initialDueDate,
                'status'                => '0',
                'is_disabled'           => '0',
                'created_at'            => $now,
            ];
            $initialGenerated = 1;
        }

        $financedBalance       = round($totalAmount - $initialAmount, 2);
        $remainingToDistribute = round($financedBalance - $keptInstallmentsTotal, 2);
        $installmentsGenerated = 0;

        if ($maxExisting < $installmentCount) {
            $sanGroup            = $this->getSanGroup();
            $initialSharesAnchor = ($initialDueDate === $frequencyBaseDate);

            if ($maxExisting === 0) {
                $anchorDate = $frequencyBaseDate;
            } else {
                $anchorDate = strcmp($frequencyBaseDate, $lastDueDate) > 0 ? $frequencyBaseDate : $lastDueDate;
            }

            for ($i = $maxExisting + 1; $i <= $installmentCount; $i++) {
                if ($maxExisting === 0) {
                    $step = $initialSharesAnchor ? $i : ($i - 1);
                } else {
                    $step = $i - $maxExisting;
                }

                $dueDate = $sanGroup->calculateDueDate($anchorDate, $frequencyCode, $step);

                if ($i < $installmentCount) {
                    $amount = $installmentAmount;
                } else {
                    $alreadyScheduled = round(($installmentCount - $maxExisting - 1) * $installmentAmount, 2);
                    $amount           = round($remainingToDistribute - $alreadyScheduled, 2);
                }

                if ($amount <= 0.0) {
                    return [
                        'status'  => 400,
                        'error'   => 'INVALID_REMAINING_BALANCE',
                        'message' => 'El saldo pendiente por financiar no permite distribuir las cuotas restantes con monto positivo',
                    ];
                }

                $desc = str_replace(
                    ['{installment_number}', '{total_installments}', '{reference}', '{code}', '{group_name}'],
                    [(string) $i, (string) $installmentCount, $code, $code, $code],
                    $baseTemplate
                );

                $toInsert[] = [
                    'branch_id'             => $branchId,
                    'client_id'             => $clientId,
                    'receivable_concept_id' => $baseConceptId,
                    'description'           => mb_substr($desc, 0, 150),
                    'origin_type'           => '1',
                    'origin_id'             => $financingId,
                    'total_amount'          => $amount,
                    'paid_amount'           => 0.00,
                    'issue_date'            => $issueDate,
                    'due_date'              => $dueDate,
                    'status'                => '0',
                    'is_disabled'           => '0',
                    'created_at'            => $now,
                ];
                $installmentsGenerated++;
            }
        }

        $this->db->transStart();

        if (!empty($unpaidIdsToDelete)) {
            $this->db->table('acc_receivable')
                ->whereIn('id', $unpaidIdsToDelete)
                ->update(['deleted_at' => $now]);
        }

        $inserted = count($toInsert);
        if ($inserted > 0) {
            $this->db->table('acc_receivable')->insertBatch($toInsert);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return ['status' => 500, 'error' => 'TRANSACTION_FAILED', 'message' => 'Error al generar cuotas en base de datos'];
        }

        $this->syncOverdue($branchId, $financingId);

        return [
            'status' => 201,
            'data'   => [
                'financing_id'           => $financingId,
                'code'                   => $code,
                'initial_generated'      => $initialGenerated,
                'installments_generated' => $installmentsGenerated,
                'generated'              => $inserted,
                'preserved_installments' => count($keptInstallments),
            ],
        ];
    }

    /**
     * Sincroniza la bandera is_overdue en fnc_financing para los financiamientos de una sucursal.
     */
    public function syncOverdue(int|string $branchId, ?int $financingId = null): array {
        $builder = $this->db->table('fnc_financing')
            ->select('id, is_overdue')
            ->where('branch_id', $branchId)
            ->where('deleted_at IS NULL');

        if ($financingId !== null) {
            $builder->where('id', $financingId);
        }

        $financings = $builder->get()->getResultArray();
        if (empty($financings)) {
            return ['processed' => 0, 'updated' => 0];
        }

        $today        = date('Y-m-d');
        $financingIds = array_map(fn($f) => (int) $f['id'], $financings);

        $overdueRows = $this->db->table('acc_receivable')
            ->select('origin_id')
            ->where('origin_type', '1')
            ->whereIn('origin_id', $financingIds)
            ->where('due_date <', $today)
            ->whereIn('status', ['0', '1'])
            ->where('deleted_at IS NULL')
            ->where('is_disabled', '0')
            ->groupBy('origin_id')
            ->get()
            ->getResultArray();

        $overdueMap = array_flip(array_column($overdueRows, 'origin_id'));
        $updated    = 0;
        $now        = date('Y-m-d H:i:s');

        foreach ($financings as $f) {
            $fid     = (int) $f['id'];
            $newFlag = isset($overdueMap[$fid]) ? '1' : '0';

            if ($f['is_overdue'] !== $newFlag) {
                $this->db->table('fnc_financing')
                    ->where('id', $fid)
                    ->update(['is_overdue' => $newFlag, 'updated_at' => $now]);
                $updated++;
            }
        }

        return ['processed' => count($financings), 'updated' => $updated];
    }

    /**
     * Sincroniza la morosidad de todos los financiamientos de un cliente en una sucursal.
     */
    public function syncOverdueForClient(int|string $branchId, int|string $clientId): void {
        $rows = $this->db->table('fnc_financing')
            ->select('id')
            ->where('branch_id', $branchId)
            ->where('client_id', $clientId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $this->syncOverdue($branchId, (int) $row['id']);
        }
    }

    /**
     * Genera la cartola / estado de cuenta individual del financiamiento con cuotas y pagos aplicados.
     */
    public function getStatement(int $branchId, int $financingId): ?array {
        $f = $this->db->table('fnc_financing as f')
            ->select(
                'f.id, f.code, f.status, f.is_overdue, f.awarded_at, f.total_amount, f.initial_amount, ' .
                'f.installment_amount, f.installment_count, f.initial_due_date, f.frequency_base_date, ' .
                'f.contract_type_id, f.benefit_metadata, f.client_id, ' .
                'fr.code as frequency_code, fr.name as frequency_name, ' .
                'ct.name as contract_type_name, ' .
                'c.first_name as client_first_name, c.last_name as client_last_name, ' .
                'c.identification_type as client_id_type, c.identification_value as client_id_val, ' .
                'c.phone_primary as client_phone'
            )
            ->join('glb_person as c', 'f.client_id = c.id', 'inner')
            ->join('fnc_frequency as fr', 'f.frequency_id = fr.id', 'left')
            ->join('fnc_contract_type as ct', 'f.contract_type_id = ct.id', 'left')
            ->where('f.id', $financingId)
            ->where('f.branch_id', $branchId)
            ->where('f.deleted_at IS NULL')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (!$f) {
            return null;
        }

        $receivables = $this->db->table('acc_receivable as r')
            ->select('r.id, r.receivable_concept_id, rc.code as concept_code, r.description, r.due_date, r.total_amount, r.paid_amount, r.status, r.created_at')
            ->join('acc_receivable_concept as rc', 'r.receivable_concept_id = rc.id', 'left')
            ->where('r.origin_type', '1')
            ->where('r.origin_id', $financingId)
            ->where('r.deleted_at IS NULL')
            ->where('r.is_disabled', '0')
            ->orderBy('r.due_date', 'ASC')
            ->orderBy('r.id', 'ASC')
            ->get()
            ->getResultArray();

        $receivableIds        = array_column($receivables, 'id');
        $paymentsByReceivable = [];

        if (!empty($receivableIds)) {
            $items = $this->db->table('acc_receipt_item as ri')
                ->select("MAX(ri.receivable_id) as receivable_id, MAX(ri.amount) as applied_amount, MAX(rc.code) as receipt_code, MAX(rc.date) as receipt_date, GROUP_CONCAT(DISTINCT pa.name SEPARATOR ', ') as account_name")
                ->join('acc_receipt as rc', 'ri.receipt_id = rc.id', 'inner')
                ->join('acc_receipt_payment as rp', 'rc.id = rp.receipt_id', 'left')
                ->join('acc_payment_account as pa', "rp.origin_id = pa.id AND rp.origin_type = '0'", 'left')
                ->whereIn('ri.receivable_id', $receivableIds)
                ->where('rc.status', '1')
                ->where('rc.deleted_at IS NULL')
                ->where('ri.deleted_at IS NULL')
                ->groupBy('ri.id')
                ->get()
                ->getResultArray();

            foreach ($items as $item) {
                $rid = (int) $item['receivable_id'];
                $paymentsByReceivable[$rid][] = [
                    'receipt_code'   => $item['receipt_code'],
                    'receipt_date'   => $item['receipt_date'],
                    'applied_amount' => (float) $item['applied_amount'],
                    'account_name'   => !empty($item['account_name']) ? $item['account_name'] : 'Caja General',
                ];
            }
        }

        $today          = date('Y-m-d');
        $totalCommitted = 0.0;
        $totalCollected = 0.0;
        $paidCount      = 0;
        $overdueCount   = 0;
        $overdueAmount  = 0.0;
        $installments   = [];

        if (empty($receivables)) {
            $totalCommitted = (float) $f['total_amount'];
        } else {
            foreach ($receivables as $r) {
                $rid     = (int) $r['id'];
                $tot     = (float) $r['total_amount'];
                $paid    = (float) $r['paid_amount'];
                $pending = max(0.0, round($tot - $paid, 2));

                $totalCommitted += $tot;
                $totalCollected += $paid;

                $isOverdue = ($r['due_date'] < $today && in_array($r['status'], ['0', '1'], true));
                if ($isOverdue) {
                    $overdueCount++;
                    $overdueAmount += $pending;
                }

                if ($r['status'] === '2') {
                    $paidCount++;
                }

                $installments[] = [
                    'id'             => $rid,
                    'concept_code'   => $r['concept_code'],
                    'is_initial'     => (($r['concept_code'] ?? '') === 'initial_payment'),
                    'description'    => $r['description'],
                    'due_date'       => $r['due_date'],
                    'total_amount'   => $tot,
                    'paid_amount'    => $paid,
                    'pending_amount' => $pending,
                    'status'         => $r['status'],
                    'is_overdue'     => $isOverdue,
                    'payments'       => $paymentsByReceivable[$rid] ?? [],
                ];
            }
        }

        $benefitMeta = null;
        if (!empty($f['benefit_metadata'])) {
            $benefitMeta = is_string($f['benefit_metadata'])
                ? json_decode($f['benefit_metadata'], true)
                : $f['benefit_metadata'];
        }

        $totalAmount   = (float) $f['total_amount'];
        $initialAmount = (float) $f['initial_amount'];

        return [
            'financing' => [
                'id'                  => (int) $f['id'],
                'code'                => $f['code'],
                'status'              => $f['status'],
                'is_overdue'          => ($overdueCount > 0 || $f['is_overdue'] === '1'),
                'is_awarded'          => !empty($f['awarded_at']),
                'awarded_at'          => $f['awarded_at'],
                'total_amount'        => $totalAmount,
                'initial_amount'      => $initialAmount,
                'financed_amount'     => round($totalAmount - $initialAmount, 2),
                'installment_amount'  => (float) $f['installment_amount'],
                'installment_count'   => (int) $f['installment_count'],
                'initial_due_date'    => $f['initial_due_date'],
                'frequency_base_date' => $f['frequency_base_date'],
                'frequency_code'      => $f['frequency_code'],
                'frequency_name'      => $f['frequency_name'],
                'contract_type_id'    => $f['contract_type_id'] ? (int) $f['contract_type_id'] : null,
                'contract_type_name'  => $f['contract_type_name'],
                'benefit_metadata'    => $benefitMeta,
            ],
            'client' => [
                'id'                   => (int) $f['client_id'],
                'name'                 => trim($f['client_first_name'] . ' ' . $f['client_last_name']),
                'identification_type'  => $f['client_id_type'],
                'identification_value' => $f['client_id_val'],
                'phone'                => $f['client_phone'],
            ],
            'summary' => [
                'total_committed'      => round($totalCommitted, 2),
                'total_collected'      => round($totalCollected, 2),
                'total_pending'        => max(0.0, round($totalCommitted - $totalCollected, 2)),
                'installments_total'   => count($installments),
                'installments_paid'    => $paidCount,
                'installments_pending' => max(0, count($installments) - $paidCount),
                'installments_overdue' => $overdueCount,
                'overdue_amount'       => round($overdueAmount, 2),
            ],
            'installments' => $installments,
        ];
    }

    private function getSanGroup(): SanGroup {
        if ($this->sanGroup === null) {
            $this->sanGroup = new SanGroup($this->db);
        }
        return $this->sanGroup;
    }
}
