<?php

namespace App\Controllers\V1\Branches\Financing;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Finance\ContractRenderer;

/**
 * Emisión y registro de firma de contratos individuales (`fnc_contract_document`) en `v1/branches/{branchId}/financing/{financingId}/contract`.
 *
 * Relación 1:1 con el financiamiento (`origin_type = '1'`) y formalización física digitalizada (`sign`).
 */
class Contract extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Finance\ContractDocumentModel';
    protected $schemaName = 'App\Schemas\Finance\ContractDocumentSchema';

    public function show($branchId = null, $financingId = null): Response {
        $financing = $this->getAncestorRelation($branchId, $financingId, false);
        if ($financing instanceof Response) {
            return $financing;
        }

        $contract = $this->getActiveContract($branchId, $financingId);
        if (!$contract) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        return $this->_read($branchId, $financingId, $contract['id']);
    }

    /**
     * Emite un nuevo contrato formalizado para el financiamiento.
     */
    public function create($branchId = null, $financingId = null): Response {
        $financing = $this->getAncestorRelation($branchId, $financingId);
        if ($financing instanceof Response) {
            return $financing;
        }

        if (empty($financing['contract_type_id'])) {
            return $this->fail('El financiamiento no tiene un tipo de contrato asignado.', 409, 'FINANCING_WITHOUT_CONTRACT_TYPE');
        }

        if (in_array($financing['status'], ['2', '3'], true)) {
            return $this->fail('El financiamiento no es elegible para emisión de contrato.', 409, 'FINANCING_NOT_ELIGIBLE');
        }

        $existing = $this->model
            ->where($this->getDirectRelationCriteria($branchId, $financingId))
            ->whereIn('sign_status', ['0', '1'])
            ->where('deleted_at IS NULL')
            ->first();

        if ($existing) {
            return $this->fail('El financiamiento ya cuenta con un contrato activo o firmado.', 409, 'CONTRACT_ALREADY_EXISTS');
        }

        $this->setCallback('itemSetValues', function (array $item) use ($branchId, $financing): array {
            $item['branch_id']        = $branchId;
            $item['contract_type_id'] = $financing['contract_type_id'];
            $item['origin_type']      = '1';
            $item['origin_id']        = $financing['id'];
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

    public function pdf($branchId = null, $financingId = null): Response {
        $financing = $this->getAncestorRelation($branchId, $financingId, false);
        if ($financing instanceof Response) {
            return $financing;
        }

        $contract = $this->getActiveContract($branchId, $financingId);
        if (!$contract) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        $renderer   = new ContractRenderer($this->model->db);
        $pdfContent = $renderer->render((int) $contract['id']);

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $contract['code'] . '.pdf"')
            ->setBody($pdfContent);
    }

    public function sign($branchId = null, $financingId = null): Response {
        $financing = $this->getAncestorRelation($branchId, $financingId);
        if ($financing instanceof Response) {
            return $financing;
        }

        $contract = $this->getActiveContract($branchId, $financingId);
        if (!$contract) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        if ($contract['sign_status'] === '1') {
            return $this->fail('El contrato ya ha sido firmado previamente.', 409, 'CONTRACT_ALREADY_SIGNED');
        }

        if ($contract['sign_status'] === '2') {
            return $this->fail('El contrato se encuentra rescindido y no puede ser firmado.', 409, 'CONTRACT_RESCINDED');
        }

        $this->setCallback('itemSetRules', function (): array {
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

        $this->setCallback('itemAfterValidation', function () use ($branchId, $financingId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId, $financingId));
        });

        return $this->baseUpdate($contract['id']);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($branchId, $financingId, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($branchId, $financingId, true));

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria(int|string $branchId, int|string $financingId, bool $isRead = false): array {
        if ($isRead) {
            return [
                ['branch_id', $branchId],
                ['origin_type', '1'],
                ['origin_id', $financingId],
            ];
        }
        return [
            'branch_id'   => $branchId,
            'origin_type' => '1',
            'origin_id'   => $financingId,
        ];
    }

    private function getActiveContract(int|string $branchId, int|string $financingId): ?array {
        return $this->model
            ->where($this->getDirectRelationCriteria($branchId, $financingId))
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function getAncestorRelation(int|string $branchId, int|string $financingId, bool $addIsDisabled = true): array|Response {
        $builder = $this->model->db->table('fnc_financing as f')
            ->select('f.id, f.branch_id, f.contract_type_id, f.status, f.client_id')
            ->join('glb_branch as b', 'f.branch_id = b.id', 'inner')
            ->where('f.id', $financingId)
            ->where('f.branch_id', $branchId)
            ->where('f.deleted_at IS NULL')
            ->where('b.deleted_at IS NULL');

        if ($addIsDisabled) {
            $builder->where('f.is_disabled', '0')
                    ->where('b.is_disabled', '0');
        }

        $financing = $builder->limit(1)->get()->getRowArray();

        if (!$financing) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        return $financing;
    }
}
