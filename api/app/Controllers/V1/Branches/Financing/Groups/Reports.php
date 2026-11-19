<?php

namespace App\Controllers\V1\Branches\Financing\Groups;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Libraries\Finance\SanReport;

/**
 * Reportes analíticos de un grupo SAM en `v1/branches/{branch_id}/financing/groups/{group_id}/reports`.
 *
 * - summary: Resumen financiero y matriz operativa del grupo SAM.
 */
class Reports extends BaseController {

    /**
     * Resumen consolidado y matriz operativa del grupo SAM.
     * GET v1/branches/{branch_id}/financing/groups/{group_id}/reports/summary
     */
    public function summary($branchId = null, $groupId = null): Response {
        if (!is_numeric($branchId) || !is_numeric($groupId)) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        $report = new SanReport();
        $data   = $report->getGroupSummary((int) $branchId, (int) $groupId);

        if ($data === null) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        return $this->respond([
            'status' => 200,
            'data'   => $data,
        ], 200);
    }
}
