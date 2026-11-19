<?php

namespace App\Controllers\V1\Branches\Financing\Groups\Participants;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Finance\ContractRenderer;

/**
 * Emisión, renderizado PDF y registro de firma de contratos SAM (`fnc_contract_document`) en `v1/branches/{branchId}/financing/groups/{groupId}/participants/{participantId}/contract`.
 *
 * Relación 1:1 con el participante, generación de PDF con TCPDF y formalización física digitalizada.
 */
class Contract extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Finance\ContractDocumentModel';
    protected $schemaName = 'App\Schemas\Finance\ContractDocumentSchema';

    public function show($branchId = null, $groupId = null, $participantId = null): Response {
        $participant = $this->getAncestorRelation($branchId, $groupId, $participantId, false);
        if ($participant instanceof Response) {
            return $participant;
        }

        $contract = $this->getActiveContract($branchId, $participantId);
        if (!$contract) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        return $this->_read($branchId, $participantId, $contract['id']);
    }

    /**
     * Emite un nuevo contrato formalizado para el participante.
     */
    public function create($branchId = null, $groupId = null, $participantId = null): Response {
        $participant = $this->getAncestorRelation($branchId, $groupId, $participantId);
        if ($participant instanceof Response) {
            return $participant;
        }

        if (empty($participant['contract_type_id'])) {
            return $this->fail('El grupo SAM no tiene un modelo de contrato asignado.', 409, 'GROUP_WITHOUT_CONTRACT_TYPE');
        }

        if (in_array($participant['status'], ['2', '3'], true)) {
            return $this->fail('El participante no es elegible para emisión de contrato.', 409, 'PARTICIPANT_NOT_ELIGIBLE');
        }

        $existing = $this->model
            ->where($this->getDirectRelationCriteria($branchId, $participantId))
            ->whereIn('sign_status', ['0', '1'])
            ->where('deleted_at IS NULL')
            ->first();

        if ($existing) {
            return $this->fail('El participante ya cuenta con un contrato activo o firmado.', 409, 'CONTRACT_ALREADY_EXISTS');
        }

        $this->setCallback('itemSetValues', function (array $item) use ($branchId, $participant): array {
            $item['branch_id']        = $branchId;
            $item['contract_type_id'] = $participant['contract_type_id'];
            $item['origin_type']      = '0';
            $item['origin_id']        = $participant['id'];
            $item['sign_status']      = '0';
            $item['is_disabled']      = '0';
            $item['issue_date']       = date('Y-m-d H:i:s');
            $item['code']             = 'CNTR-' . str_pad((string) $branchId, 2, '0', STR_PAD_LEFT) . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            return $item;
        });

        $raw = (string) $this->request->getBody();
        if (trim($raw) !== '') {
            $input = $this->getInput(false);
            if ($input instanceof Response) {
                return $input;
            }
        }

        return $this->baseCreate([]);
    }

    public function pdf($branchId = null, $groupId = null, $participantId = null): Response {
        $participant = $this->getAncestorRelation($branchId, $groupId, $participantId, false);
        if ($participant instanceof Response) {
            return $participant;
        }

        $contract = $this->getActiveContract($branchId, $participantId);
        if (!$contract) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        $renderer = new ContractRenderer($this->model->db);
        $pdfContent = $renderer->render((int) $contract['id']);

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $contract['code'] . '.pdf"')
            ->setBody($pdfContent);
    }

    public function sign($branchId = null, $groupId = null, $participantId = null): Response {
        $participant = $this->getAncestorRelation($branchId, $groupId, $participantId);
        if ($participant instanceof Response) {
            return $participant;
        }

        $contract = $this->getActiveContract($branchId, $participantId);
        if (!$contract) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        if ($contract['sign_status'] === '1') {
            return $this->fail('El contrato ya ha sido firmado previamente.', 409, 'CONTRACT_ALREADY_SIGNED');
        }

        if ($contract['sign_status'] === '2') {
            return $this->fail('El contrato se encuentra rescindido y no puede ser firmado.', 409, 'CONTRACT_RESCINDED');
        }

        $this->setCallback('itemSetRules', function (array $rules, array $item): array {
            return [
                'id'          => 'permit_empty|is_natural_no_zero',
                'file_url'    => 'required|string|valid_url_strict|max_length[255]',
                'signed_date' => 'required|valid_date[Y-m-d]',
                'sign_status' => 'permit_empty|in_list[0,1,2]',
            ];
        });

        $this->setCallback('itemSetValues', function (array $item): array {
            unset(
                $item['branch_id'],
                $item['contract_type_id'],
                $item['origin_type'],
                $item['origin_id'],
                $item['code'],
                $item['issue_date']
            );
            $item['sign_status'] = '1';
            return $item;
        });

        $this->setCallback('itemAfterValidation', function () use ($branchId, $participantId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId, $participantId));
        });

        return $this->baseUpdate($contract['id']);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($branchId, $participantId, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($branchId, $participantId, true));

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria(int|string $branchId, int|string $participantId, bool $isRead = false): array {
        if ($isRead) {
            return [
                ['branch_id', $branchId],
                ['origin_type', '0'],
                ['origin_id', $participantId],
            ];
        }
        return [
            'branch_id'   => $branchId,
            'origin_type' => '0',
            'origin_id'   => $participantId,
        ];
    }

    private function getActiveContract($branchId, $participantId): ?array {
        return $this->model
            ->where($this->getDirectRelationCriteria($branchId, $participantId))
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function getAncestorRelation(int|string $branchId, int|string $groupId, int|string $participantId, bool $addIsDisabled = true): array|Response {
        $builder = $this->model->db->table('fnc_san_participant as sp')
            ->select('sp.id, sp.branch_id, sp.san_group_id, sp.status, sp.client_id, sg.contract_type_id')
            ->join('fnc_san_group as sg', 'sp.san_group_id = sg.id', 'inner')
            ->join('glb_branch as b', 'sp.branch_id = b.id', 'inner')
            ->where('sp.id', $participantId)
            ->where('sp.branch_id', $branchId)
            ->where('sp.san_group_id', $groupId)
            ->where('sp.deleted_at IS NULL')
            ->where('sg.deleted_at IS NULL')
            ->where('b.deleted_at IS NULL');

        if ($addIsDisabled) {
            $builder->where('sg.is_disabled', '0')
                    ->where('b.is_disabled', '0');
        }

        $participant = $builder->limit(1)->get()->getRowArray();

        if (!$participant) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        return $participant;
    }

    private function getParticipant($branchId, $groupId, $participantId, bool $addIsDisabled = true): array|Response {
        return $this->getAncestorRelation($branchId, $groupId, $participantId, $addIsDisabled);
    }
}
