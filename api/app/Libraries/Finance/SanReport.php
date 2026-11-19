<?php

namespace App\Libraries\Finance;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * Servicio de reportería y análisis para financiamiento SAM (fnc_san_group / fnc_san_participant).
 *
 * Consultas analíticas optimizadas y agregaciones SQL en bloque (Zero N+1):
 * - Resumen consolidado de grupo SAM (métricas financieras y matriz de participantes).
 * - Cartera de morosos con contacto de referencia (quien refirió al cliente) y alerta de beneficio entregado.
 * - Calendario de proyección de cobros agrupado por fecha de vencimiento.
 * - Reporte de beneficios adjudicados y semáforo de solvencia posterior a la entrega.
 * - Estado de cuenta (cartola) individual del participante con historial de recibos.
 */
class SanReport {
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null) {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Resumen financiero y métricas consolidadas de todos los grupos SAM de una sucursal.
     */
    public function getBranchSummary(int $branchId): array {
        $groups = $this->db->table('fnc_san_group as g')
            ->select('g.id, g.branch_id, g.name, g.installment_amount, g.installment_count, g.participant_max, g.participant_count, g.frequency_base_date, g.status')
            ->where('g.branch_id', $branchId)
            ->where('g.deleted_at IS NULL')
            ->orderBy('g.name', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($groups)) {
            return [
                'financial' => [
                    'total_committed'       => 0.0,
                    'total_collected'       => 0.0,
                    'total_pending'         => 0.0,
                    'total_overdue'         => 0.0,
                    'collection_percentage' => 0.0,
                ],
                'operational' => [
                    'groups_total'            => 0,
                    'groups_active'           => 0,
                    'participants_total'      => 0,
                    'participants_max'        => 0,
                    'participants_up_to_date' => 0,
                    'participants_overdue'    => 0,
                    'awarded_total'           => 0,
                    'pending_award_total'     => 0,
                ],
                'groups' => [],
            ];
        }

        $participants = $this->db->table('fnc_san_participant as sp')
            ->select('sp.id, sp.san_group_id, sp.position, sp.installment_amount, sp.installment_count, sp.status, sp.is_overdue, sp.awarded_at')
            ->where('sp.branch_id', $branchId)
            ->where('sp.deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $partsByGroup = [];
        $partIds = [];
        foreach ($participants as $p) {
            $partsByGroup[(int) $p['san_group_id']][] = $p;
            $partIds[] = (int) $p['id'];
        }

        $today = date('Y-m-d');
        $receivablesAgg = [];
        if (!empty($partIds)) {
            $rows = $this->db->table('acc_receivable')
                ->select("origin_id,
                          SUM(total_amount) as total_committed,
                          SUM(paid_amount) as total_collected,
                          COUNT(*) as installments_count,
                          SUM(CASE WHEN status = '2' THEN 1 ELSE 0 END) as paid_count,
                          SUM(CASE WHEN due_date < '{$today}' AND status IN ('0', '1') THEN (total_amount - paid_amount) ELSE 0 END) as overdue_amount,
                          SUM(CASE WHEN due_date < '{$today}' AND status IN ('0', '1') THEN 1 ELSE 0 END) as overdue_count")
                ->where('origin_type', '0')
                ->whereIn('origin_id', $partIds)
                ->where('deleted_at IS NULL')
                ->where('is_disabled', '0')
                ->groupBy('origin_id')
                ->get()
                ->getResultArray();

            foreach ($rows as $r) {
                $receivablesAgg[(int) $r['origin_id']] = $r;
            }
        }

        // Consultar recibos de apertura asociados a los grupos si no hay cuentas amortizadas
        $openingRows = $this->db->table('acc_receipt as r')
            ->select('r.code, rp.amount')
            ->join('acc_receipt_payment as rp', 'r.id = rp.receipt_id')
            ->where('r.branch_id', $branchId)
            ->like('r.code', 'REC-SAM-', 'after')
            ->where('r.status', '1')
            ->where('r.deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $openingByGroupAndPos = [];
        foreach ($openingRows as $or) {
            if (preg_match('/^REC-SAM-([A-Z0-9]+)-(\d+)$/', $or['code'], $m)) {
                $openingByGroupAndPos[$m[1]][(int) $m[2]] = (float) $or['amount'];
            }
        }

        $branchCommitted   = 0.0;
        $branchCollected   = 0.0;
        $branchOverdue     = 0.0;
        $branchUpToDate    = 0;
        $branchOverdueCnt  = 0;
        $branchAwarded     = 0;
        $branchMaxCap      = 0;
        $groupsActive      = 0;
        $groupSummaries    = [];

        foreach ($groups as $g) {
            $gid = (int) $g['id'];
            if ($g['status'] === '1') {
                $groupsActive++;
            }
            $branchMaxCap += (int) $g['participant_max'];
            $cleanCode = str_replace('Ñ', 'NN', preg_replace('/[^A-Z0-9Ñ]/u', '', strtoupper($g['name'])));

            $gCommitted = 0.0;
            $gCollected = 0.0;
            $gOverdue   = 0.0;
            $gOverdueParticipants = 0;
            $gAwarded   = 0;
            $gParts     = $partsByGroup[$gid] ?? [];

            foreach ($gParts as $p) {
                $pid = (int) $p['id'];
                $pos = (int) $p['position'];

                $instAmount = !empty($p['installment_amount']) ? (float) $p['installment_amount'] : (float) $g['installment_amount'];
                $instCount  = !empty($p['installment_count']) ? (int) $p['installment_count'] : (int) $g['installment_count'];
                $pCommitted = (float) ($instAmount * $instCount);

                $r = $receivablesAgg[$pid] ?? null;
                if ($r) {
                    $pCollected = (float) $r['total_collected'];
                    $pOverdue   = (float) $r['overdue_amount'];
                    $pOverdueCnt = (int) $r['overdue_count'];
                } else {
                    $pCollected = (float) ($openingByGroupAndPos[$cleanCode][$pos] ?? 0.0);
                    $pOverdue   = ($p['is_overdue'] === '1') ? (float) ($instAmount * 2) : 0.0;
                    $pOverdueCnt = ($p['is_overdue'] === '1') ? 2 : 0;
                }

                $gCommitted += $pCommitted;
                $gCollected += $pCollected;
                $gOverdue   += $pOverdue;

                if ($pOverdueCnt > 0 || $p['is_overdue'] === '1') {
                    $gOverdueParticipants++;
                    $branchOverdueCnt++;
                } else {
                    $branchUpToDate++;
                }

                if (!empty($p['awarded_at'])) {
                    $gAwarded++;
                    $branchAwarded++;
                }
            }

            $gCommitted = round($gCommitted, 2);
            $gCollected = round($gCollected, 2);
            $gPending   = max(0.0, round($gCommitted - $gCollected, 2));
            $gPct       = $gCommitted > 0 ? round(($gCollected / $gCommitted) * 100, 2) : 0.0;

            $branchCommitted += $gCommitted;
            $branchCollected += $gCollected;
            $branchOverdue   += $gOverdue;

            $groupSummaries[] = [
                'id'                    => $gid,
                'name'                  => $g['name'],
                'status'                => $g['status'],
                'participant_count'     => count($gParts),
                'participant_max'       => (int) $g['participant_max'],
                'total_committed'       => $gCommitted,
                'total_collected'       => $gCollected,
                'total_pending'         => $gPending,
                'total_overdue'         => round($gOverdue, 2),
                'collection_percentage' => $gPct,
                'overdue_participants'  => $gOverdueParticipants,
                'awarded_count'         => $gAwarded,
            ];
        }

        $branchCommitted = round($branchCommitted, 2);
        $branchCollected = round($branchCollected, 2);
        $branchPending   = max(0.0, round($branchCommitted - $branchCollected, 2));
        $branchPct       = $branchCommitted > 0 ? round(($branchCollected / $branchCommitted) * 100, 2) : 0.0;

        return [
            'financial' => [
                'total_committed'       => $branchCommitted,
                'total_collected'       => $branchCollected,
                'total_pending'         => $branchPending,
                'total_overdue'         => round($branchOverdue, 2),
                'collection_percentage' => $branchPct,
            ],
            'operational' => [
                'groups_total'            => count($groups),
                'groups_active'           => $groupsActive,
                'participants_total'      => count($participants),
                'participants_max'        => $branchMaxCap,
                'participants_up_to_date' => $branchUpToDate,
                'participants_overdue'    => $branchOverdueCnt,
                'awarded_total'           => $branchAwarded,
                'pending_award_total'     => max(0, count($participants) - $branchAwarded),
            ],
            'groups' => $groupSummaries,
        ];
    }

    /**
     * Resumen financiero y matriz operativa de un grupo SAM.
     */
    public function getGroupSummary(int $branchId, int $groupId): ?array {
        $group = $this->db->table('fnc_san_group as g')
            ->select('g.id, g.branch_id, g.name, g.installment_amount, g.installment_count, g.participant_max, g.participant_count, g.frequency_base_date, g.frequency_id, g.status')
            ->where('g.id', $groupId)
            ->where('g.branch_id', $branchId)
            ->where('g.deleted_at IS NULL')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (!$group) {
            return null;
        }

        // Obtener todos los participantes del grupo con cliente y referente
        $participants = $this->db->table('fnc_san_participant as sp')
            ->select('sp.id, sp.san_group_id, sp.client_id, sp.referrer_id, sp.position, sp.installment_amount, sp.installment_count, sp.status, sp.is_overdue, sp.awarded_at, sp.benefit_metadata, ' .
                     'c.first_name as client_first_name, c.last_name as client_last_name, c.identification_type as client_id_type, c.identification_value as client_id_val, c.phone_primary as client_phone, ' .
                     'ref.first_name as ref_first_name, ref.last_name as ref_last_name, ref.phone_primary as ref_phone')
            ->join('glb_person as c', 'sp.client_id = c.id', 'left')
            ->join('glb_person as ref', 'sp.referrer_id = ref.id', 'left')
            ->where('sp.san_group_id', $groupId)
            ->where('sp.branch_id', $branchId)
            ->where('sp.deleted_at IS NULL')
            ->orderBy('sp.position', 'ASC')
            ->get()
            ->getResultArray();

        $participantIds = array_column($participants, 'id');

        // Consultar recibos de apertura asociados al grupo si la cartera aún no amortiza en acc_receivable
        $groupCleanCode = str_replace('Ñ', 'NN', preg_replace('/[^A-Z0-9Ñ]/u', '', strtoupper($group['name'])));
        $openingRows = $this->db->table('acc_receipt as r')
            ->select('r.code, rp.amount')
            ->join('acc_receipt_payment as rp', 'r.id = rp.receipt_id')
            ->where('r.branch_id', $branchId)
            ->like('r.code', "REC-SAM-{$groupCleanCode}-", 'after')
            ->where('r.status', '1')
            ->where('r.deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $openingByPos = [];
        foreach ($openingRows as $or) {
            if (preg_match('/-(\d+)$/', $or['code'], $m)) {
                $openingByPos[(int) $m[1]] = (float) $or['amount'];
            }
        }

        // Consultar agregados de cartera (cuotas) en una sola consulta
        $receivablesAgg = [];
        if (!empty($participantIds)) {
            $today = date('Y-m-d');
            $rows  = $this->db->table('acc_receivable')
                ->select("origin_id, 
                          SUM(total_amount) as total_committed, 
                          SUM(paid_amount) as total_collected, 
                          COUNT(*) as installments_count, 
                          SUM(CASE WHEN status = '2' THEN 1 ELSE 0 END) as paid_count, 
                          SUM(CASE WHEN due_date < '{$today}' AND status IN ('0', '1') THEN (total_amount - paid_amount) ELSE 0 END) as overdue_amount, 
                          SUM(CASE WHEN due_date < '{$today}' AND status IN ('0', '1') THEN 1 ELSE 0 END) as overdue_count")
                ->where('origin_type', '0')
                ->whereIn('origin_id', $participantIds)
                ->where('deleted_at IS NULL')
                ->where('is_disabled', '0')
                ->groupBy('origin_id')
                ->get()
                ->getResultArray();

            foreach ($rows as $r) {
                $receivablesAgg[(int) $r['origin_id']] = $r;
            }
        }

        $groupCommitted = 0.0;
        $groupCollected = 0.0;
        $upToDateCount  = 0;
        $overdueCount   = 0;
        $awardedCount   = 0;
        $participantList = [];

        foreach ($participants as $p) {
            $pid   = (int) $p['id'];
            $pos   = (int) $p['position'];
            $agg   = $receivablesAgg[$pid] ?? null;

            $instCount  = (int) ($p['installment_count'] ?? $group['installment_count']);
            $instAmount = (float) ($p['installment_amount'] ?? $group['installment_amount']);

            // Si hay cuotas generadas, usamos su suma; de lo contrario calculamos el compromiso esperado
            $pCommitted = $agg ? (float) $agg['total_committed'] : ($instCount * $instAmount);
            
            // Recaudado: desde acc_receivable o fallback al recibo de apertura
            $collectedFromRecs    = $agg ? (float) $agg['total_collected'] : 0.0;
            $collectedFromOpening = $openingByPos[$pos] ?? 0.0;
            $pCollected           = max($collectedFromRecs, $collectedFromOpening);
            $pPending             = max(0.0, round($pCommitted - $pCollected, 2));

            $pOverdueCount  = $agg ? (int) $agg['overdue_count'] : ($p['is_overdue'] === '1' ? 1 : 0);
            $pOverdueAmount = $agg ? (float) $agg['overdue_amount'] : ($p['is_overdue'] === '1' ? $pPending : 0.0);
            $pPaidCount     = $agg ? (int) $agg['paid_count'] : ($instAmount > 0 ? (int) floor($pCollected / $instAmount) : 0);
            $pTotalCount    = $agg ? (int) $agg['installments_count'] : $instCount;

            $isAwarded = !empty($p['awarded_at']);
            if ($isAwarded) {
                $awardedCount++;
            }

            if ($pOverdueCount > 0 || $p['is_overdue'] === '1') {
                $overdueCount++;
            } else {
                $upToDateCount++;
            }

            $groupCommitted += $pCommitted;
            $groupCollected += $pCollected;

            $benefitMeta = null;
            if (!empty($p['benefit_metadata'])) {
                $benefitMeta = is_string($p['benefit_metadata']) ? json_decode($p['benefit_metadata'], true) : $p['benefit_metadata'];
            }

            $participantList[] = [
                'id'                     => $pid,
                'position'               => (int) $p['position'],
                'status'                 => $p['status'],
                'is_overdue'             => ($pOverdueCount > 0 || $p['is_overdue'] === '1'),
                'is_awarded'             => $isAwarded,
                'awarded_at'             => $p['awarded_at'],
                'benefit_metadata'       => $benefitMeta,
                'client' => [
                    'id'                   => (int) $p['client_id'],
                    'name'                 => trim(($p['client_first_name'] ?? '') . ' ' . ($p['client_last_name'] ?? '')),
                    'identification_type'  => $p['client_id_type'],
                    'identification_value' => $p['client_id_val'],
                    'phone'                => $p['client_phone'],
                ],
                'referrer' => !empty($p['referrer_id']) ? [
                    'id'                   => (int) $p['referrer_id'],
                    'name'                 => trim(($p['ref_first_name'] ?? '') . ' ' . ($p['ref_last_name'] ?? '')),
                    'phone'                => $p['ref_phone'],
                ] : null,
                'financial' => [
                    'installment_amount'       => $instAmount,
                    'installment_count'        => $instCount,
                    'total_committed'          => round($pCommitted, 2),
                    'total_collected'          => round($pCollected, 2),
                    'total_pending'            => $pPending,
                    'paid_installments'        => $pPaidCount,
                    'total_installments'       => $pTotalCount,
                    'overdue_installments'     => $pOverdueCount,
                    'overdue_amount'           => round($pOverdueAmount, 2),
                ],
            ];
        }

        $groupCommitted = round($groupCommitted, 2);
        $groupCollected = round($groupCollected, 2);
        $groupPending   = max(0.0, round($groupCommitted - $groupCollected, 2));
        $collectionPct  = $groupCommitted > 0 ? round(($groupCollected / $groupCommitted) * 100, 2) : 0.0;

        return [
            'group' => [
                'id'                  => (int) $group['id'],
                'name'                => $group['name'],
                'status'              => $group['status'],
                'frequency_base_date' => $group['frequency_base_date'],
                'participant_max'     => (int) $group['participant_max'],
                'participant_count'   => count($participants),
                'default_installment_amount' => (float) $group['installment_amount'],
                'default_installment_count'  => (int) $group['installment_count'],
            ],
            'financial' => [
                'total_committed'       => $groupCommitted,
                'total_collected'       => $groupCollected,
                'total_pending'         => $groupPending,
                'collection_percentage' => $collectionPct,
            ],
            'operational' => [
                'participants_total'      => count($participants),
                'participants_max'        => (int) $group['participant_max'],
                'participants_up_to_date' => $upToDateCount,
                'participants_overdue'    => $overdueCount,
                'awarded_count'           => $awardedCount,
                'pending_award_count'     => max(0, count($participants) - $awardedCount),
            ],
            'participants' => $participantList,
        ];
    }

    /**
     * Reporte consolidado de morosos y cobranza con datos de contacto de referencia.
     */
    public function getDefaulters(int $branchId, ?int $groupId = null): array {
        $today = date('Y-m-d');

        // 1. Obtener todos los participantes de la sucursal
        $pBuilder = $this->db->table('fnc_san_participant as sp')
            ->select("sp.id as participant_id,
                      sp.san_group_id,
                      sp.position,
                      sp.installment_amount,
                      sp.installment_count,
                      sp.frequency_base_date,
                      sp.is_overdue,
                      sp.awarded_at,
                      sp.benefit_metadata,
                      g.name as group_name,
                      g.installment_amount as group_installment_amount,
                      g.installment_count as group_installment_count,
                      c.id as client_id,
                      c.first_name as client_first_name,
                      c.last_name as client_last_name,
                      c.identification_type as client_id_type,
                      c.identification_value as client_id_val,
                      c.phone_primary as client_phone,
                      c.phone_secondary as client_phone_sec,
                      ref.id as ref_id,
                      ref.first_name as ref_first_name,
                      ref.last_name as ref_last_name,
                      ref.phone_primary as ref_phone")
            ->join('fnc_san_group as g', 'sp.san_group_id = g.id', 'inner')
            ->join('glb_person as c', 'sp.client_id = c.id', 'inner')
            ->join('glb_person as ref', 'sp.referrer_id = ref.id', 'left')
            ->where('sp.branch_id', $branchId)
            ->where('sp.deleted_at IS NULL')
            ->where('g.deleted_at IS NULL');

        if ($groupId !== null) {
            $pBuilder->where('sp.san_group_id', $groupId);
        }

        $participants = $pBuilder->get()->getResultArray();
        if (empty($participants)) {
            return [];
        }

        $pids = array_column($participants, 'participant_id');

        // 2. Consultar cuotas vencidas en acc_receivable para todos los participantes (Zero N+1)
        $overdueRecs = [];
        if (!empty($pids)) {
            $recRows = $this->db->table('acc_receivable')
                ->select("origin_id,
                          COUNT(id) as overdue_count,
                          SUM(total_amount - paid_amount) as overdue_amount,
                          MIN(due_date) as oldest_due_date")
                ->where('branch_id', $branchId)
                ->where('origin_type', '0')
                ->whereIn('origin_id', $pids)
                ->where('due_date <', $today)
                ->whereIn('status', ['0', '1'])
                ->where('deleted_at IS NULL')
                ->where('is_disabled', '0')
                ->groupBy('origin_id')
                ->get()
                ->getResultArray();

            foreach ($recRows as $rr) {
                $overdueRecs[(int) $rr['origin_id']] = $rr;
            }
        }

        // 3. Consultar recibos de apertura para calcular abonos cuando no hay cuotas
        $openingRows = $this->db->table('acc_receipt as r')
            ->select('r.code, rp.amount')
            ->join('acc_receipt_payment as rp', 'r.id = rp.receipt_id')
            ->where('r.branch_id', $branchId)
            ->like('r.code', 'REC-SAM-', 'after')
            ->where('r.status', '1')
            ->where('r.deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $openingByCode = [];
        foreach ($openingRows as $or) {
            $openingByCode[$or['code']] = (float) $or['amount'];
        }

        $defaulters = [];
        foreach ($participants as $row) {
            $pid = (int) $row['participant_id'];
            $recOverdue = $overdueRecs[$pid] ?? null;

            $hasOverdueFlag = ($row['is_overdue'] === '1');
            $hasOverdueRecs = ($recOverdue !== null && (int) $recOverdue['overdue_count'] > 0);

            if (!$hasOverdueFlag && !$hasOverdueRecs) {
                continue; // No está moroso
            }

            $instAmount = (float) ($row['installment_amount'] ?? $row['group_installment_amount']);
            $instCount  = (int) ($row['installment_count'] ?? $row['group_installment_count']);
            $committed  = $instCount * $instAmount;

            $groupCleanCode = str_replace('Ñ', 'NN', preg_replace('/[^A-Z0-9Ñ]/u', '', strtoupper($row['group_name'])));
            $code = sprintf('REC-SAM-%s-%02d', $groupCleanCode, (int) $row['position']);
            $openingPaid = $openingByCode[$code] ?? 0.0;

            if ($hasOverdueRecs) {
                $overdueAmount = round((float) $recOverdue['overdue_amount'], 2);
                $overdueCount  = (int) $recOverdue['overdue_count'];
                $oldestDate    = $recOverdue['oldest_due_date'];
            } else {
                $overdueAmount = max(0.0, round($committed - $openingPaid, 2));
                $overdueCount  = $instAmount > 0 ? (int) ceil($overdueAmount / $instAmount) : 1;
                $oldestDate    = !empty($row['frequency_base_date']) ? $row['frequency_base_date'] : $today;
            }

            $daysDiff = (int) (new \DateTime($today))->diff(new \DateTime($oldestDate))->days;

            $benefitMeta = null;
            if (!empty($row['benefit_metadata'])) {
                $benefitMeta = is_string($row['benefit_metadata']) ? json_decode($row['benefit_metadata'], true) : $row['benefit_metadata'];
            }

            $benefitSummary = null;
            if ($benefitMeta && is_array($benefitMeta)) {
                $textParts = [];
                foreach (['brand', 'model', 'vehicle_model', 'color', 'description'] as $k) {
                    if (!empty($benefitMeta[$k]) && is_scalar($benefitMeta[$k])) {
                        $textParts[] = $benefitMeta[$k];
                    }
                }
                $benefitSummary = !empty($textParts) ? implode(' - ', array_unique($textParts)) : null;
            }

            $defaulters[] = [
                'participant_id'       => $pid,
                'group_id'             => (int) $row['san_group_id'],
                'group_name'           => $row['group_name'],
                'position'             => (int) $row['position'],
                'overdue_installments' => $overdueCount,
                'overdue_amount'       => $overdueAmount,
                'oldest_due_date'      => $oldestDate,
                'days_overdue'         => $daysDiff,
                'is_awarded'           => !empty($row['awarded_at']),
                'awarded_at'           => $row['awarded_at'],
                'benefit_summary'      => $benefitSummary,
                'client' => [
                    'id'                   => (int) $row['client_id'],
                    'name'                 => trim($row['client_first_name'] . ' ' . $row['client_last_name']),
                    'identification_type'  => $row['client_id_type'],
                    'identification_value' => $row['client_id_val'],
                    'phone_primary'        => $row['client_phone'],
                    'phone_secondary'      => $row['client_phone_sec'],
                ],
                'referrer' => !empty($row['ref_id']) ? [
                    'id'                   => (int) $row['ref_id'],
                    'name'                 => trim($row['ref_first_name'] . ' ' . $row['ref_last_name']),
                    'phone'                => $row['ref_phone'],
                ] : null,
            ];
        }

        usort($defaulters, fn($a, $b) => $b['overdue_amount'] <=> $a['overdue_amount']);
        return $defaulters;
    }

    /**
     * Calendario de proyección de cobros para un rango de fechas.
     */
    public function getCollectionCalendar(int $branchId, string $startDate, string $endDate, ?int $groupId = null): array {
        $builder = $this->db->table('acc_receivable as r')
            ->select("r.id as receivable_id,
                      r.description,
                      r.due_date,
                      r.total_amount,
                      r.paid_amount,
                      r.status,
                      sp.id as participant_id,
                      sp.san_group_id,
                      sp.position,
                      g.name as group_name,
                      c.id as client_id,
                      c.first_name as client_first_name,
                      c.last_name as client_last_name,
                      c.phone_primary as client_phone,
                      ref.first_name as ref_first_name,
                      ref.last_name as ref_last_name,
                      ref.phone_primary as ref_phone")
            ->join('fnc_san_participant as sp', 'r.origin_id = sp.id', 'inner')
            ->join('fnc_san_group as g', 'sp.san_group_id = g.id', 'inner')
            ->join('glb_person as c', 'r.client_id = c.id', 'inner')
            ->join('glb_person as ref', 'sp.referrer_id = ref.id', 'left')
            ->where('r.branch_id', $branchId)
            ->where('r.origin_type', '0')
            ->where('r.due_date >=', $startDate)
            ->where('r.due_date <=', $endDate)
            ->where('r.deleted_at IS NULL')
            ->where('r.is_disabled', '0')
            ->where('sp.deleted_at IS NULL')
            ->where('g.deleted_at IS NULL');

        if ($groupId !== null) {
            $builder->where('sp.san_group_id', $groupId);
        }

        $rows = $builder->orderBy('r.due_date', 'ASC')
            ->orderBy('g.name', 'ASC')
            ->orderBy('sp.position', 'ASC')
            ->get()
            ->getResultArray();

        $totalExpected  = 0.0;
        $totalCollected = 0.0;
        $paidCount      = 0;
        $pendingCount   = 0;
        $days           = [];

        foreach ($rows as $row) {
            $date    = $row['due_date'];
            $tot     = (float) $row['total_amount'];
            $paid    = (float) $row['paid_amount'];
            $pending = max(0.0, round($tot - $paid, 2));

            $totalExpected  += $tot;
            $totalCollected += $paid;

            if ($row['status'] === '2') {
                $paidCount++;
            } else {
                $pendingCount++;
            }

            if (!isset($days[$date])) {
                $days[$date] = [
                    'date'           => $date,
                    'count'          => 0,
                    'total_expected' => 0.0,
                    'total_collected'=> 0.0,
                    'total_pending'  => 0.0,
                    'items'          => [],
                ];
            }

            $days[$date]['count']++;
            $days[$date]['total_expected']  += $tot;
            $days[$date]['total_collected'] += $paid;
            $days[$date]['total_pending']   += $pending;

            $days[$date]['items'][] = [
                'receivable_id'   => (int) $row['receivable_id'],
                'participant_id'  => (int) $row['participant_id'],
                'group_id'        => (int) $row['san_group_id'],
                'group_name'      => $row['group_name'],
                'position'        => (int) $row['position'],
                'description'     => $row['description'],
                'total_amount'    => $tot,
                'paid_amount'     => $paid,
                'pending_amount'  => $pending,
                'status'          => $row['status'],
                'client' => [
                    'id'    => (int) $row['client_id'],
                    'name'  => trim($row['client_first_name'] . ' ' . $row['client_last_name']),
                    'phone' => $row['client_phone'],
                ],
                'referrer' => !empty($row['ref_first_name']) ? [
                    'name'  => trim($row['ref_first_name'] . ' ' . ($row['ref_last_name'] ?? '')),
                    'phone' => $row['ref_phone'],
                ] : null,
            ];
        }

        // Redondear totales en cada día
        foreach ($days as &$d) {
            $d['total_expected']  = round($d['total_expected'], 2);
            $d['total_collected'] = round($d['total_collected'], 2);
            $d['total_pending']   = round($d['total_pending'], 2);
        }
        unset($d);

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
            'summary' => [
                'total_expected'        => round($totalExpected, 2),
                'total_collected'       => round($totalCollected, 2),
                'total_pending'         => max(0.0, round($totalExpected - $totalCollected, 2)),
                'installments_total'    => count($rows),
                'installments_paid'     => $paidCount,
                'installments_pending'  => $pendingCount,
            ],
            'days' => $days,
        ];
    }

    /**
     * Reporte de beneficios adjudicados/entregados con estado de solvencia actual.
     */
    public function getAwardedBenefits(int $branchId, ?int $groupId = null): array {
        $builder = $this->db->table('fnc_san_participant as sp')
            ->select("sp.id as participant_id,
                      sp.san_group_id,
                      sp.position,
                      sp.awarded_at,
                      sp.benefit_metadata,
                      sp.is_overdue,
                      sp.status,
                      g.name as group_name,
                      c.id as client_id,
                      c.first_name as client_first_name,
                      c.last_name as client_last_name,
                      c.identification_type as client_id_type,
                      c.identification_value as client_id_val,
                      c.phone_primary as client_phone,
                      ref.first_name as ref_first_name,
                      ref.last_name as ref_last_name,
                      ref.phone_primary as ref_phone")
            ->join('fnc_san_group as g', 'sp.san_group_id = g.id', 'inner')
            ->join('glb_person as c', 'sp.client_id = c.id', 'inner')
            ->join('glb_person as ref', 'sp.referrer_id = ref.id', 'left')
            ->where('sp.branch_id', $branchId)
            ->where('sp.awarded_at IS NOT NULL')
            ->where('sp.deleted_at IS NULL')
            ->where('g.deleted_at IS NULL');

        if ($groupId !== null) {
            $builder->where('sp.san_group_id', $groupId);
        }

        $participants = $builder->orderBy('sp.awarded_at', 'DESC')
            ->get()
            ->getResultArray();

        if (empty($participants)) {
            return [];
        }

        $pids = array_column($participants, 'participant_id');
        $today = date('Y-m-d');

        // Consultar cartera de los adjudicados en una sola consulta
        $aggByPid = [];
        if (!empty($pids)) {
            $receivablesRows = $this->db->table('acc_receivable')
                ->select("origin_id,
                          SUM(total_amount) as total_committed,
                          SUM(paid_amount) as total_collected,
                          SUM(CASE WHEN due_date < '{$today}' AND status IN ('0', '1') THEN 1 ELSE 0 END) as overdue_count,
                          SUM(CASE WHEN due_date < '{$today}' AND status IN ('0', '1') THEN (total_amount - paid_amount) ELSE 0 END) as overdue_amount")
                ->where('origin_type', '0')
                ->whereIn('origin_id', $pids)
                ->where('deleted_at IS NULL')
                ->where('is_disabled', '0')
                ->groupBy('origin_id')
                ->get()
                ->getResultArray();

            foreach ($receivablesRows as $r) {
                $aggByPid[(int) $r['origin_id']] = $r;
            }
        }

        $results = [];
        foreach ($participants as $p) {
            $pid = (int) $p['participant_id'];
            $agg = $aggByPid[$pid] ?? null;

            $committed = $agg ? (float) $agg['total_committed'] : 0.0;
            $collected = $agg ? (float) $agg['total_collected'] : 0.0;
            $overdueCnt = $agg ? (int) $agg['overdue_count'] : 0;
            $overdueAmt = $agg ? (float) $agg['overdue_amount'] : 0.0;

            $benefitMeta = null;
            if (!empty($p['benefit_metadata'])) {
                $benefitMeta = is_string($p['benefit_metadata']) ? json_decode($p['benefit_metadata'], true) : $p['benefit_metadata'];
            }

            $results[] = [
                'participant_id'   => $pid,
                'group_id'         => (int) $p['san_group_id'],
                'group_name'       => $p['group_name'],
                'position'         => (int) $p['position'],
                'awarded_at'       => $p['awarded_at'],
                'is_overdue'       => ($overdueCnt > 0 || $p['is_overdue'] === '1'),
                'overdue_count'    => $overdueCnt,
                'overdue_amount'   => round($overdueAmt, 2),
                'total_committed'  => round($committed, 2),
                'total_collected'  => round($collected, 2),
                'benefit_metadata' => $benefitMeta,
                'client' => [
                    'id'                   => (int) $p['client_id'],
                    'name'                 => trim($p['client_first_name'] . ' ' . $p['client_last_name']),
                    'identification_type'  => $p['client_id_type'],
                    'identification_value' => $p['client_id_val'],
                    'phone'                => $p['client_phone'],
                ],
                'referrer' => !empty($p['ref_first_name']) ? [
                    'name'  => trim($p['ref_first_name'] . ' ' . ($p['ref_last_name'] ?? '')),
                    'phone' => $p['ref_phone'],
                ] : null,
            ];
        }

        return $results;
    }

    /**
     * Estado de cuenta (cartola) individual del participante con detalle de cuotas y recibos.
     */
    public function getParticipantStatement(int $branchId, int $participantId): ?array {
        $p = $this->db->table('fnc_san_participant as sp')
            ->select("sp.id, sp.san_group_id, sp.client_id, sp.referrer_id, sp.position, sp.installment_amount, sp.installment_count, sp.status, sp.is_overdue, sp.awarded_at, sp.benefit_metadata, " .
                     "g.name as group_name, g.installment_amount as group_installment_amount, g.installment_count as group_installment_count, " .
                     "c.first_name as client_first_name, c.last_name as client_last_name, c.identification_type as client_id_type, c.identification_value as client_id_val, c.phone_primary as client_phone, " .
                     "ref.first_name as ref_first_name, ref.last_name as ref_last_name, ref.phone_primary as ref_phone")
            ->join('fnc_san_group as g', 'sp.san_group_id = g.id', 'inner')
            ->join('glb_person as c', 'sp.client_id = c.id', 'inner')
            ->join('glb_person as ref', 'sp.referrer_id = ref.id', 'left')
            ->where('sp.id', $participantId)
            ->where('sp.branch_id', $branchId)
            ->where('sp.deleted_at IS NULL')
            ->where('g.deleted_at IS NULL')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (!$p) {
            return null;
        }

        // Obtener cuotas generadas
        $receivables = $this->db->table('acc_receivable')
            ->select('id, description, due_date, total_amount, paid_amount, status, created_at')
            ->where('origin_type', '0')
            ->where('origin_id', $participantId)
            ->where('deleted_at IS NULL')
            ->where('is_disabled', '0')
            ->orderBy('due_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $receivableIds = array_column($receivables, 'id');

        // Obtener pagos / recibos aplicados a estas cuotas (Zero N+1)
        $paymentsByReceivable = [];
        if (!empty($receivableIds)) {
            $items = $this->db->table('acc_receipt_item as ri')
                ->select("ri.receivable_id, ri.amount as applied_amount, rc.code as receipt_code, rc.date as receipt_date, rc.status as receipt_status, pa.name as account_name")
                ->join('acc_receipt as rc', 'ri.receipt_id = rc.id', 'inner')
                ->join('acc_receipt_payment as rp', 'rc.id = rp.receipt_id', 'left')
                ->join('acc_payment_account as pa', "rp.origin_id = pa.id AND rp.origin_type = '0'", 'left')
                ->whereIn('ri.receivable_id', $receivableIds)
                ->where('rc.status', '1') // Solo recibos confirmados
                ->where('rc.deleted_at IS NULL')
                ->get()
                ->getResultArray();

            foreach ($items as $item) {
                $rid = (int) $item['receivable_id'];
                $paymentsByReceivable[$rid][] = [
                    'receipt_code'   => $item['receipt_code'],
                    'receipt_date'   => $item['receipt_date'],
                    'applied_amount' => (float) $item['applied_amount'],
                    'account_name'   => $item['account_name'] ?? 'Caja General',
                ];
            }
        }

        $today = date('Y-m-d');
        $totalCommitted = 0.0;
        $totalCollected = 0.0;
        $paidCount      = 0;
        $overdueCount   = 0;
        $overdueAmount  = 0.0;
        $installments   = [];

        if (empty($receivables)) {
            $groupCleanCode = str_replace('Ñ', 'NN', preg_replace('/[^A-Z0-9Ñ]/u', '', strtoupper($p['group_name'])));
            $receiptCode    = sprintf('REC-SAM-%s-%02d', $groupCleanCode, (int) $p['position']);
            $openingReceipt = $this->db->table('acc_receipt as r')
                ->select('r.code as receipt_code, r.date as receipt_date, rp.amount as applied_amount, pa.name as account_name')
                ->join('acc_receipt_payment as rp', 'r.id = rp.receipt_id', 'inner')
                ->join('acc_payment_account as pa', "rp.origin_id = pa.id AND rp.origin_type = '0'", 'left')
                ->where('r.code', $receiptCode)
                ->where('r.branch_id', $branchId)
                ->where('r.status', '1')
                ->limit(1)
                ->get()
                ->getRowArray();

            $instCount      = (int) ($p['installment_count'] ?? $p['group_installment_count']);
            $instAmount     = (float) ($p['installment_amount'] ?? $p['group_installment_amount']);
            $totalCommitted = $instCount * $instAmount;
            $totalCollected = $openingReceipt ? (float) $openingReceipt['applied_amount'] : 0.0;
            $paidCount      = $instAmount > 0 ? (int) floor($totalCollected / $instAmount) : 0;
            $overdueCount   = ($p['is_overdue'] === '1') ? 1 : 0;
            $overdueAmount  = ($p['is_overdue'] === '1') ? max(0.0, round($totalCommitted - $totalCollected, 2)) : 0.0;
        }

        foreach ($receivables as $r) {
            $rid     = (int) $r['id'];
            $tot     = (float) $r['total_amount'];
            $paid    = (float) $r['paid_amount'];
            $pending = max(0.0, round($tot - $paid, 2));

            $totalCommitted += $tot;
            $totalCollected += $paid;

            $isOverdue = ($r['due_date'] < $today && in_array($r['status'], ['0', '1']));
            if ($isOverdue) {
                $overdueCount++;
                $overdueAmount += $pending;
            }

            if ($r['status'] === '2') {
                $paidCount++;
            }

            $installments[] = [
                'id'             => $rid,
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

        $benefitMeta = null;
        if (!empty($p['benefit_metadata'])) {
            $benefitMeta = is_string($p['benefit_metadata']) ? json_decode($p['benefit_metadata'], true) : $p['benefit_metadata'];
        }

        $instCount  = (int) ($p['installment_count'] ?? $p['group_installment_count']);
        $instAmount = (float) ($p['installment_amount'] ?? $p['group_installment_amount']);

        return [
            'participant' => [
                'id'                 => (int) $p['id'],
                'group_id'           => (int) $p['san_group_id'],
                'group_name'         => $p['group_name'],
                'position'           => (int) $p['position'],
                'status'             => $p['status'],
                'is_overdue'         => ($overdueCount > 0 || $p['is_overdue'] === '1'),
                'is_awarded'         => !empty($p['awarded_at']),
                'awarded_at'         => $p['awarded_at'],
                'benefit_metadata'   => $benefitMeta,
                'installment_amount' => $instAmount,
                'installment_count'  => $instCount,
            ],
            'client' => [
                'id'                   => (int) $p['client_id'],
                'name'                 => trim($p['client_first_name'] . ' ' . $p['client_last_name']),
                'identification_type'  => $p['client_id_type'],
                'identification_value' => $p['client_id_val'],
                'phone'                => $p['client_phone'],
            ],
            'referrer' => !empty($p['referrer_id']) ? [
                'id'    => (int) $p['referrer_id'],
                'name'  => trim(($p['ref_first_name'] ?? '') . ' ' . ($p['ref_last_name'] ?? '')),
                'phone' => $p['ref_phone'],
            ] : null,
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
}
