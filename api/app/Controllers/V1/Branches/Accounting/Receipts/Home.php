<?php

namespace App\Controllers\V1\Branches\Accounting\Receipts;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Accounting\Receipt;

/**
 * Cabecera y ciclo de vida de recibos de cobro (acc_receipt).
 *
 * Definición por sucursal (v1/branches/{branch_id}/accounting/receipts).
 *
 * - Crear: emite recibo en borrador ('0'). Inyecta code automático,
 *   branch_id y collector_id autenticado.
 * - Actualizar: permite modificar únicamente notas ('notes') mientras el recibo esté en borrador ('0').
 * - Leer (index): listado de recibos mediante ReceiptSchema filtrados por branch_id.
 * - Consultar (show): consulta unitaria estándar mediante ReceiptSchema filtrada por branch_id.
 * - Detalle (detail): consulta consolidada completa (JSON con items, pagos, créditos y totales con caché de 5 min).
 * - Documento (pdf): representación en PDF (Formato Carta) basada en el detalle consolidado.
 * - Estado (status): PUT (:num)/status. Transiciona estado:
 *   • '1' (Confirmar): corre cascada FIFO, imputa pagos a obligaciones y genera saldo a favor si hay excedente.
 *   • '2' (Anular): revierte amortizaciones, cancela créditos no consumidos y exige motivo de anulación.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Accounting\ReceiptModel';
    protected $schemaName = 'App\Schemas\Accounting\ReceiptSchema';
    private ?Receipt $receipt = null;

    public function create($branchId = null): Response {
        $this->setCallback('setRules', function (array $rules): array {
            unset($rules['status'], $rules['cancellation_reason']);
            return $rules;
        });

        $this->setCallback('itemSetValues', function (array $item) use ($branchId): array {
            $item['branch_id']    = (int) $branchId;
            $item['collector_id'] = (int) (service('context')->get('auth:userId') ?? 1);
            $item['status']       = '0';
            $item['date']         = !empty($item['date']) ? $item['date'] : date('Y-m-d');

            $item['code']         = 'RECP-' . str_pad((string) $branchId, 2, '0', STR_PAD_LEFT) . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            return $item;
        });

        return $this->baseCreate();
    }

    public function index($branchId = null): Response {
        return $this->_read($branchId);
    }

    public function show($branchId = null, $id = null): Response {
        return $this->_read($branchId, $id);
    }

    /**
     * Consulta el detalle consolidado del comprobante (JSON completo con items, pagos y totales, con caché de 5 min).
     */
    public function detail($branchId = null, $id = null): Response {
        if (!is_numeric($branchId) || !is_numeric($id)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 404,
                'error'   => 'RECEIPT_NOT_FOUND',
                'message' => lang('Receipt.receipt_not_found'),
            ]);
        }

        $detail = $this->model->getDetailByCache((int) $branchId, (int) $id);

        if (empty($detail)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 404,
                'error'   => 'RECEIPT_NOT_FOUND',
                'message' => lang('Receipt.receipt_not_found'),
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'status' => 200,
            'data'   => $detail,
        ]);
    }

    /**
     * Genera la representación en PDF del comprobante de cobro (Formato Carta).
     */
    public function pdf($branchId = null, $id = null): Response {
        if (!is_numeric($branchId) || !is_numeric($id)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 404,
                'error'   => 'RECEIPT_NOT_FOUND',
                'message' => lang('Receipt.receipt_not_found'),
            ]);
        }

        $detail = $this->model->getDetailByCache((int) $branchId, (int) $id);

        if (empty($detail)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 404,
                'error'   => 'RECEIPT_NOT_FOUND',
                'message' => lang('Receipt.receipt_not_found'),
            ]);
        }

        $template = (string) ($this->request->getGet('template') ?? 'formal');
        $renderer  = new \App\Libraries\Accounting\ReceiptRenderer();
        $pdfBinary = $renderer->render($detail, $template);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . ($detail['code'] ?? 'recibo') . '.pdf"')
            ->setBody($pdfBinary);
    }

    public function update($branchId = null, $id = null): Response {
        $receipt = $this->model->where($this->getDirectRelationCriteria($branchId))->find($id);

        if (!$receipt) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 404,
                'error'   => 'RECEIPT_NOT_FOUND',
                'message' => lang('Receipt.receipt_not_found'),
            ]);
        }

        if ($receipt['status'] !== '0') {
            return $this->response->setStatusCode(409)->setJSON([
                'status'  => 409,
                'error'   => 'RECEIPT_NOT_DRAFT',
                'message' => lang('Receipt.receipt_not_draft'),
            ]);
        }

        $this->setCallback('itemAfterValidation', function () use ($branchId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId));
        });

        $this->setCallback('setRules', function (array $rules): array {
            return [
                'id'    => 'permit_empty|is_natural_no_zero',
                'notes' => 'permit_empty|string',
            ];
        });

        $this->setCallback('after', function (array $response) use ($branchId, $id): void {
            if (($response['affected'] ?? 0) > 0) {
                $this->model->removeDetailByCache((int) $branchId, (int) $id);
            }
        });

        return $this->baseUpdate($id);
    }

    public function status($branchId = null, $id = null): Response {
        $input = $this->getInput(true);
        $newStatus = (string) ($input['status'] ?? '');
        $reason    = isset($input['cancellation_reason']) ? (string) $input['cancellation_reason'] : null;

        $result = $this->getReceipt()->changeStatus((int) $branchId, (int) $id, $newStatus, $reason);

        $statusCode = $result['status'] ?? 200;
        if ($statusCode === 200) {
            $this->model->removeDetailByCache((int) $branchId, (int) $id);
        }

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($branchId, $id = null, bool $returnArray = false, ?int $limit = null): Response|array|null {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($branchId, true));

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria(int|string $branchId, bool $isRead = false): array {
        if ($isRead) {
            return [['branch_id', $branchId]];
        }
        return ['branch_id' => $branchId];
    }

    private function getReceipt(): Receipt {
        if ($this->receipt === null) {
            $this->receipt = new Receipt($this->model->db);
        }
        return $this->receipt;
    }
}
