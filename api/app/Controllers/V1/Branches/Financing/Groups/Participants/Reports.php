<?php

namespace App\Controllers\V1\Branches\Financing\Groups\Participants;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Libraries\Finance\SanReport;

/**
 * Reportes individuales de un participante SAM en `v1/branches/{branch_id}/financing/groups/{group_id}/participants/{participant_id}/reports`.
 *
 * - statement: Estado de cuenta (cartola) individual con desglose de cuotas y recibos aplicados.
 */
class Reports extends BaseController {

    /**
     * Estado de cuenta individual (cartola) de un participante SAM.
     * GET v1/branches/{branch_id}/financing/groups/{group_id}/participants/{participant_id}/reports/statement
     */
    public function statement($branchId = null, $groupId = null, $participantId = null): Response {
        if (!is_numeric($branchId) || !is_numeric($groupId) || !is_numeric($participantId)) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $report = new SanReport();
        $data   = $report->getParticipantStatement((int) $branchId, (int) $participantId);

        if ($data === null || (int) ($data['participant']['group_id'] ?? 0) !== (int) $groupId) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        return $this->respond([
            'status' => 200,
            'data'   => $data,
        ], 200);
    }
}
