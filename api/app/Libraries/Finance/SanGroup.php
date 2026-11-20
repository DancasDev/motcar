<?php

namespace App\Libraries\Finance;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * Servicio de financiamiento para Grupos SAM (fnc_san_group / fnc_san_participant).
 *
 * Responsabilidades:
 * - Generación de cuotas periódicas de aportes a partir de plantilla de concepto.
 * - Sincronización de morosidad (is_overdue) basada en vencimientos.
 * - Calificación de suscriptores para asambleas de adjudicación / sorteo.
 */
class SanGroup {
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null) {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Genera la cartera de cuentas por cobrar para un grupo SAM activo.
     */
    public function generateReceivables(int $branchId, int $groupId, ?int $participantId = null): array {
        $group = $this->db->table('fnc_san_group')
            ->where('id', $groupId)
            ->where('branch_id', $branchId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if (!$group) {
            return ['status' => 404, 'error' => 'GROUP_NOT_FOUND', 'message' => 'Grupo SAM no encontrado'];
        }

        $builder = $this->db->table('fnc_san_participant')
            ->where('san_group_id', $groupId)
            ->where('status', '1')
            ->where('deleted_at IS NULL')
            ->where('is_disabled', '0');

        if ($participantId !== null) {
            $builder->where('id', $participantId);
        }

        $participants = $builder->get()->getResultArray();

        if (empty($participants)) {
            return ['status' => 400, 'error' => 'NO_ACTIVE_PARTICIPANTS', 'message' => 'El grupo no cuenta con participantes activos'];
        }

        $concept = $this->db->table('acc_receivable_concept')->where('code', 'base_installment')->get()->getRowArray();
        $conceptId = $concept ? (int) $concept['id'] : 1;
        $template  = $concept['template'] ?? 'Cuota {installment_number} - {reference}';

        $groupName = $group['name'];
        $now       = date('Y-m-d H:i:s');
        $toInsert  = [];
        $unpaidIdsToDelete = [];

        // Consultar todas las cuotas existentes para los participantes del grupo en una sola consulta (Zero N+1)
        $participantIds = array_map(fn($p) => (int) $p['id'], $participants);
        $existingRows = $this->db->table('acc_receivable')
            ->select('id, origin_id, description, due_date, total_amount, paid_amount, status')
            ->where('origin_type', '0')
            ->where('receivable_concept_id', $conceptId)
            ->whereIn('origin_id', $participantIds)
            ->where('deleted_at IS NULL')
            ->orderBy('due_date', 'ASC')
            ->get()
            ->getResultArray();

        $existingByParticipant = [];
        foreach ($existingRows as $row) {
            $existingByParticipant[(int) $row['origin_id']][] = $row;
        }

        foreach ($participants as $p) {
            $clientId          = (int) $p['client_id'];
            $pid               = (int) $p['id'];
            $installmentCount  = (int) ($p['installment_count'] ?? $group['installment_count']);
            $installmentAmount = (float) ($p['installment_amount'] ?? $group['installment_amount']);
            $frequencyCode     = $this->getFrequencyCode((int) ($p['frequency_id'] ?? $group['frequency_id']));
            $userBaseDate      = !empty($p['frequency_base_date']) ? $p['frequency_base_date'] : $group['frequency_base_date'];

            $existingCuotas = $existingByParticipant[$pid] ?? [];

            // Cuotas que se preservan (pagadas completo o con abono parcial) vs no pagadas
            $keptCuotas = [];
            foreach ($existingCuotas as $row) {
                if ((float) $row['paid_amount'] > 0.0 || $row['status'] === '2') {
                    $keptCuotas[] = $row;
                } elseif ($row['status'] === '0' && (float) $row['paid_amount'] == 0.0) {
                    $unpaidIdsToDelete[] = (int) $row['id'];
                }
            }

            // Deducir el número ordinal máximo de cuotas preservadas y la última fecha de vencimiento
            $maxExisting = 0;
            $lastDueDate = null;

            foreach ($keptCuotas as $k => $row) {
                $num = null;
                if (preg_match('/(?:cuota|partida|#)\s*#?(\d+)/i', $row['description'], $matches)) {
                    $num = (int) $matches[1];
                } else {
                    $num = $k + 1;
                }

                if ($num > $maxExisting) {
                    $maxExisting = $num;
                }

                if ($lastDueDate === null || strcmp($row['due_date'], $lastDueDate) > 0) {
                    $lastDueDate = $row['due_date'];
                }
            }

            // Ancla de fecha
            if ($maxExisting === 0) {
                $anchorDate = !empty($userBaseDate) ? $userBaseDate : date('Y-m-d');
            } else {
                $anchorDate = (!empty($p['frequency_base_date']) && strcmp($p['frequency_base_date'], $lastDueDate) > 0)
                    ? $p['frequency_base_date']
                    : $lastDueDate;
            }

            for ($i = $maxExisting + 1; $i <= $installmentCount; $i++) {
                $step    = ($maxExisting === 0) ? ($i - 1) : ($i - $maxExisting);
                $dueDate = $this->calculateDueDate($anchorDate, $frequencyCode, $step);

                $desc = str_replace(
                    ['{installment_number}', '{total_installments}', '{group_name}', '{reference}'],
                    [(string) $i, (string) $installmentCount, $groupName, $groupName],
                    $template
                );

                $toInsert[] = [
                    'branch_id'             => $branchId,
                    'client_id'             => $clientId,
                    'receivable_concept_id' => $conceptId,
                    'description'           => $desc,
                    'origin_type'           => '0',
                    'origin_id'             => $pid,
                    'total_amount'          => $installmentAmount,
                    'paid_amount'           => 0.00,
                    'issue_date'            => date('Y-m-d'),
                    'due_date'              => $dueDate,
                    'status'                => '0',
                    'is_disabled'           => '0',
                    'created_at'            => $now,
                ];
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

        $this->syncOverdue($branchId, $participantId);

        return [
            'status' => 201,
            'data'   => [
                'group_id'     => $groupId,
                'participants' => count($participants),
                'installments' => $group['installment_count'],
                'generated'    => $inserted,
            ],
        ];
    }

    /**
     * Sincroniza la bandera is_overdue en fnc_san_participant para los participantes de una sucursal.
     */
    public function syncOverdue(int|string $branchId, ?int $participantId = null): array {
        $builder = $this->db->table('fnc_san_participant');
        $builder->where('branch_id', $branchId);
        if ($participantId !== null) {
            $builder->where('id', $participantId);
        }
        $participants = $builder->where('deleted_at IS NULL')->get()->getResultArray();

        if (empty($participants)) {
            return ['processed' => 0, 'updated' => 0];
        }

        $today          = date('Y-m-d');
        $participantIds = array_map(fn($p) => (int) $p['id'], $participants);

        // Una sola consulta para determinar qué participantes tienen mora (Zero N+1)
        $overdueRows = $this->db->table('acc_receivable')
            ->select('origin_id')
            ->where('origin_type', '0')
            ->whereIn('origin_id', $participantIds)
            ->where('due_date <', $today)
            ->whereIn('status', ['0', '1'])
            ->where('deleted_at IS NULL')
            ->where('is_disabled', '0')
            ->groupBy('origin_id')
            ->get()
            ->getResultArray();

        $overduePids = array_flip(array_column($overdueRows, 'origin_id'));
        $updated     = 0;
        $now         = date('Y-m-d H:i:s');

        foreach ($participants as $p) {
            $pid     = (int) $p['id'];
            $newFlag = isset($overduePids[$pid]) ? '1' : '0';

            if ($p['is_overdue'] !== $newFlag) {
                $this->db->table('fnc_san_participant')
                    ->where('id', $pid)
                    ->update(['is_overdue' => $newFlag, 'updated_at' => $now]);
                $updated++;
            }
        }

        return ['processed' => count($participants), 'updated' => $updated];
    }

    /**
     * Sincroniza la morosidad de todos los cupos SAM de un cliente en una sucursal.
     */
    public function syncOverdueForClient(int|string $branchId, int|string $clientId): void {
        $participants = $this->db->table('fnc_san_participant')
            ->where('branch_id', $branchId)
            ->where('client_id', $clientId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        foreach ($participants as $p) {
            $this->syncOverdue($branchId, (int) $p['id']);
        }
    }

    /**
     * Obtiene los participantes calificados para asamblea / sorteo:
     * is_overdue = '0' AND status = '1' AND awarded_at IS NULL
     */
    public function getQualifiedParticipants(int $branchId, int $groupId): array {
        $this->syncOverdue($branchId);

        return $this->db->table('fnc_san_participant as sp')
            ->select('sp.id, sp.position, sp.status, sp.is_overdue, sp.awarded_at, p.first_name, p.last_name, p.identification_type, p.identification_value')
            ->join('glb_person as p', 'sp.client_id = p.id', 'left')
            ->where('sp.branch_id', $branchId)
            ->where('sp.san_group_id', $groupId)
            ->where('sp.status', '1')
            ->where('sp.is_overdue', '0')
            ->where('sp.awarded_at IS NULL')
            ->where('sp.deleted_at IS NULL')
            ->where('sp.is_disabled', '0')
            ->orderBy('sp.position', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function getFrequencyCode(int $frequencyId): string {
        $row = $this->db->table('fnc_frequency')->select('code')->where('id', $frequencyId)->get()->getRow();
        return $row->code ?? 'monthly';
    }

    public function calculateDueDate(string $baseDate, string $frequencyCode, int $periodOffset): string {
        if ($periodOffset <= 0) {
            return $baseDate;
        }

        $baseDt = new \DateTime($baseDate);

        switch ($frequencyCode) {
            case 'weekly':
                $dt = clone $baseDt;
                $dt->modify("+{$periodOffset} weeks");
                return $dt->format('Y-m-d');

            case 'biweekly':
                return $this->calculateBiweeklyDueDate($baseDt, $periodOffset);

            case 'monthly':
            default:
                $baseDay = (int) $baseDt->format('j');
                $targetDt = new \DateTime($baseDt->format('Y-m-01'));
                $targetDt->modify("+{$periodOffset} months");
                $targetDay = min($baseDay, (int) $targetDt->format('t'));
                return $targetDt->format('Y-m-') . str_pad((string) $targetDay, 2, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Calcula la fecha de vencimiento quincenal.
     * - Si inicia el 15 o fin de mes: alterna estrictamente entre día 15 y último día natural de cada mes.
     * - Si inicia en cualquier otra fecha: suma 15 días a la fecha base para fijar los dos días del mes y los repite.
     */
    private function calculateBiweeklyDueDate(\DateTime $baseDt, int $periodOffset): string {
        $baseDay = (int) $baseDt->format('j');
        $daysInBaseMonth = (int) $baseDt->format('t');
        $isStandardBiweekly = ($baseDay === 15 || $baseDay === $daysInBaseMonth);

        if ($isStandardBiweekly) {
            $startedOnLastDay = ($baseDay === $daysInBaseMonth);
            if ($startedOnLastDay) {
                $adj = $periodOffset - 1;
                $monthOffset = 1 + intdiv($adj, 2);
                $is15 = ($adj % 2 === 0);
            } else {
                $monthOffset = intdiv($periodOffset, 2);
                $is15 = ($periodOffset % 2 === 0);
            }

            $targetDt = new \DateTime($baseDt->format('Y-m-01'));
            if ($monthOffset > 0) {
                $targetDt->modify("+{$monthOffset} months");
            }

            $targetDay = $is15 ? 15 : (int) $targetDt->format('t');
            return $targetDt->format('Y-m-') . str_pad((string) $targetDay, 2, '0', STR_PAD_LEFT);
        }

        // Caso personalizado (fecha base no es 15 ni fin de mes):
        // Se calcula la segunda fecha sumando 15 días para fijar los dos días de pago
        $secondDt = (clone $baseDt)->modify('+15 days');
        $d1 = $baseDay;
        $d2 = (int) $secondDt->format('j');

        if ($d1 <= $d2) {
            $monthOffset = intdiv($periodOffset, 2);
            $day = ($periodOffset % 2 === 0) ? $d1 : $d2;
        } else {
            $monthOffset = 1 + intdiv($periodOffset - 1, 2);
            $day = ($periodOffset % 2 === 1) ? $d2 : $d1;
        }

        $targetDt = new \DateTime($baseDt->format('Y-m-01'));
        if ($monthOffset > 0) {
            $targetDt->modify("+{$monthOffset} months");
        }
        $targetDay = min($day, (int) $targetDt->format('t'));

        return $targetDt->format('Y-m-') . str_pad((string) $targetDay, 2, '0', STR_PAD_LEFT);
    }
}
