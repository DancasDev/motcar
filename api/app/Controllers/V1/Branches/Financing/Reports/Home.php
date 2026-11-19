<?php

namespace App\Controllers\V1\Branches\Financing\Reports;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Libraries\Finance\SanReport;

/**
 * Reportes analíticos transversales de financiamiento por sucursal en `v1/branches/{branch_id}/financing/reports`.
 *
 * Consultas analíticas globales (Zero N+1) para auditoría y proyección de la cartera:
 * - defaulters: Cartera general de morosos con contacto de referencia (quien lo refirió con $15) y alerta de beneficio entregado.
 * - calendar: Proyección y calendario de cobros quincenales/mensuales de la sucursal por fecha de vencimiento.
 * - awardedBenefits: Historial de beneficios entregados/adjudicados y estatus de solvencia actual del adjudicado.
 */
class Home extends BaseController {

    /**
     * Resumen financiero y métricas consolidadas de la sucursal.
     * GET v1/branches/{branch_id}/financing/reports/summary
     */
    public function summary($branchId = null): Response {
        if (!ctype_digit((string) $branchId) || (int) $branchId <= 0) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $report = new SanReport();
        $data   = $report->getBranchSummary((int) $branchId);

        return $this->respond([
            'status' => 200,
            'data'   => $data,
        ], 200);
    }

    /**
     * Cartera general de participantes en mora con contacto de referencia.
     * GET v1/branches/{branch_id}/financing/reports/defaulters?group_id={group_id}
     */
    public function defaulters($branchId = null): Response {
        if (!ctype_digit((string) $branchId) || (int) $branchId <= 0) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $groupId = $this->request->getGet('group_id');
        $validGroupId = (is_string($groupId) && ctype_digit($groupId) && (int) $groupId > 0) ? (int) $groupId : null;
        $report  = new SanReport();
        $data    = $report->getDefaulters((int) $branchId, $validGroupId);

        return $this->respond([
            'status' => 200,
            'data'   => $data,
            'count'  => count($data),
        ], 200);
    }

    /**
     * Calendario de proyección de cobros de la sucursal para un rango de fechas.
     * GET v1/branches/{branch_id}/financing/reports/collection-calendar?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&group_id={group_id}
     */
    public function calendar($branchId = null): Response {
        if (!ctype_digit((string) $branchId) || (int) $branchId <= 0) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $startDate = $this->request->getGet('start_date') ?? date('Y-m-01');
        $endDate   = $this->request->getGet('end_date') ?? date('Y-m-t');

        $dStart = \DateTime::createFromFormat('Y-m-d', (string) $startDate);
        $dEnd   = \DateTime::createFromFormat('Y-m-d', (string) $endDate);

        if (!$dStart || $dStart->format('Y-m-d') !== $startDate ||
            !$dEnd || $dEnd->format('Y-m-d') !== $endDate ||
            $startDate > $endDate ||
            $dStart->diff($dEnd)->days > 366
        ) {
            return $this->fail('Rango de fechas inválido (debe tener formato YYYY-MM-DD, fecha inicial menor o igual a final y máximo 366 días)', 400, 'VALIDATION_ERROR');
        }

        $groupId   = $this->request->getGet('group_id');
        $validGroupId = (is_string($groupId) && ctype_digit($groupId) && (int) $groupId > 0) ? (int) $groupId : null;

        $report = new SanReport();
        $data   = $report->getCollectionCalendar(
            (int) $branchId,
            $startDate,
            $endDate,
            $validGroupId
        );

        return $this->respond([
            'status' => 200,
            'data'   => $data,
        ], 200);
    }

    /**
     * Padrón general de beneficios entregados/adjudicados con solvencia posterior.
     * GET v1/branches/{branch_id}/financing/reports/awarded-benefits?group_id={group_id}
     */
    public function awardedBenefits($branchId = null): Response {
        if (!ctype_digit((string) $branchId) || (int) $branchId <= 0) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $groupId = $this->request->getGet('group_id');
        $validGroupId = (is_string($groupId) && ctype_digit($groupId) && (int) $groupId > 0) ? (int) $groupId : null;
        $report  = new SanReport();
        $data    = $report->getAwardedBenefits((int) $branchId, $validGroupId);

        return $this->respond([
            'status' => 200,
            'data'   => $data,
            'count'  => count($data),
        ], 200);
    }

    /**
     * Estado de cuenta (cartola) transversal de un participante por sucursal.
     * GET v1/branches/{branch_id}/financing/reports/participant-statement?participant_id={participant_id}
     */
    public function participantStatement($branchId = null): Response {
        if (!ctype_digit((string) $branchId) || (int) $branchId <= 0) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $participantId = $this->request->getGet('participant_id');
        if (!is_string($participantId) || !ctype_digit($participantId) || (int) $participantId <= 0) {
            return $this->fail('Parámetro participant_id inválido o requerido', 400, 'VALIDATION_ERROR');
        }

        $report = new SanReport();
        $data   = $report->getParticipantStatement((int) $branchId, (int) $participantId);
        if ($data === null) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        return $this->respond([
            'status' => 200,
            'data'   => $data,
        ], 200);
    }
}
