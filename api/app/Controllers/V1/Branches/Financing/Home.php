<?php

namespace App\Controllers\V1\Branches\Financing;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Finance\Financing;
use App\Libraries\Metadata\Metadata;
use App\Models\Finance\ContractTypeModel;

/**
 * Financiamientos individuales por sucursal (`fnc_financing`) en `v1/branches/{branch_id}/financing`.
 *
 * - Valida reglas de saldo e inicial (`initial_amount < total_amount`, residuo positivo en última cuota) y metadatos dinámicos según `contract_type_id`.
 * - Sincroniza en cascada la eliminación lógica/física y restauración de `acc_receivable` (`origin_type = '1'`), bloqueando borrado si existen pagos o recibos asociados.
 * - Expone `generateReceivables` para generar la cuota inicial en la primera fecha y el plan de cuotas ordinarias desde `frequency_base_date`.
 */
class Home extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Finance\FinancingModel';
    protected $schemaName = 'App\Schemas\Finance\FinancingSchema';

    public function create($branchId = null): Response {
        $branch = $this->getAncestorRelation($branchId);
        if ($branch instanceof Response) {
            return $branch;
        }

        $this->setCallback('itemSetValues', function (array $item) use ($branchId): array {
            $item['branch_id'] = $branchId;
            unset($item['is_overdue']);

            $item = $this->normalizeNullableFields($item);

            if (empty($item['code'])) {
                $item['code'] = 'FIN-' . str_pad((string) $branchId, 2, '0', STR_PAD_LEFT) . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            }

            if (!array_key_exists('installment_amount', $item) || $item['installment_amount'] === null || $item['installment_amount'] === '') {
                $calculated = $this->calculateInstallmentAmount(
                    $item['total_amount'] ?? null,
                    $item['initial_amount'] ?? null,
                    $item['installment_count'] ?? null
                );
                if ($calculated !== null) {
                    $item['installment_amount'] = $calculated;
                }
            }

            return $item;
        });

        $this->setCallback('itemAfterValidation', function (array $validItem, array $rawItem) {
            $financialError = $this->validateFinancialInvariants(
                $validItem['total_amount'],
                $validItem['initial_amount'],
                $validItem['installment_count'],
                $validItem['installment_amount'] ?? null
            );
            if ($financialError !== null) {
                return $financialError;
            }

            $contractTypeId = $validItem['contract_type_id'] ?? null;
            if (empty($contractTypeId)) {
                if (array_key_exists('benefit_metadata', $validItem) && $validItem['benefit_metadata'] !== null) {
                    $validItem['benefit_metadata'] = null;
                    return $validItem;
                }
                return;
            }

            $metadata = $this->getMetadata($contractTypeId);
            if ($metadata !== null && array_key_exists('benefit_metadata', $rawItem)) {
                return $metadata->process($validItem);
            }
        });

        return $this->baseCreate();
    }

    public function index($branchId = null): Response {
        return $this->_read($branchId);
    }

    public function show($branchId = null, $id = null): Response {
        if (!is_numeric($id)) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }
        return $this->_read($branchId, $id);
    }

    public function update($branchId = null, $id = null): Response {
        $branch = $this->getAncestorRelation($branchId);
        if ($branch instanceof Response) {
            return $branch;
        }

        $existingMap          = [];
        $hasReceivablesMap    = [];
        $hasActiveContractMap = [];

        $this->setCallback('before', function (array $input) use ($branchId, $id, &$existingMap, &$hasReceivablesMap, &$hasActiveContractMap): void {
            $isBatch = !is_numeric($id);
            $ids     = $isBatch ? array_column($input, 'id') : [$id];
            $ids     = array_values(array_unique(array_filter($ids, 'is_numeric')));

            if (empty($ids)) {
                return;
            }

            $rows = $this->model
                ->select('id, client_id, contract_type_id, total_amount, initial_amount, installment_count, installment_amount, initial_due_date, frequency_base_date')
                ->where('branch_id', $branchId)
                ->whereIn('id', $ids)
                ->findAll();
            $existingMap = array_column($rows, null, 'id');

            $recRows = $this->model->db->table('acc_receivable')
                ->select('origin_id')
                ->where('branch_id', $branchId)
                ->where('origin_type', '1')
                ->whereIn('origin_id', $ids)
                ->where('deleted_at IS NULL')
                ->groupBy('origin_id')
                ->get()
                ->getResultArray();
            $hasReceivablesMap = array_flip(array_column($recRows, 'origin_id'));

            $contractRows = $this->model->db->table('fnc_contract_document')
                ->select('origin_id')
                ->where('branch_id', $branchId)
                ->where('origin_type', '1')
                ->whereIn('origin_id', $ids)
                ->whereIn('sign_status', ['0', '1'])
                ->where('deleted_at IS NULL')
                ->groupBy('origin_id')
                ->get()
                ->getResultArray();
            $hasActiveContractMap = array_flip(array_column($contractRows, 'origin_id'));
        });

        $this->setCallback('itemSetValues', function (array $item) use (&$existingMap): array {
            unset($item['branch_id'], $item['is_overdue']);
            $item = $this->normalizeNullableFields($item);

            $financialTouched = array_key_exists('total_amount', $item)
                || array_key_exists('initial_amount', $item)
                || array_key_exists('installment_count', $item);

            if ($financialTouched && (!array_key_exists('installment_amount', $item) || $item['installment_amount'] === null || $item['installment_amount'] === '')) {
                $itemId   = $item['id'] ?? null;
                $existing = $itemId !== null ? ($existingMap[$itemId] ?? null) : null;
                if ($existing !== null) {
                    $calculated = $this->calculateInstallmentAmount(
                        array_key_exists('total_amount', $item) ? $item['total_amount'] : $existing['total_amount'],
                        array_key_exists('initial_amount', $item) ? $item['initial_amount'] : $existing['initial_amount'],
                        array_key_exists('installment_count', $item) ? $item['installment_count'] : $existing['installment_count']
                    );
                    if ($calculated !== null) {
                        $item['installment_amount'] = $calculated;
                    }
                }
            }

            return $item;
        });

        $this->setCallback('itemSetRules', function (array $rules, array $item): array {
            foreach (['status', 'is_disabled'] as $nonNullEnum) {
                if (array_key_exists($nonNullEnum, $item)) {
                    $rules[$nonNullEnum] = str_replace('permit_empty', 'required', $rules[$nonNullEnum]);
                }
            }

            if (array_key_exists('installment_amount', $item)) {
                $rules['installment_amount'] = 'required|decimal|greater_than[0]|less_than_equal_to[999999999999.99]';
            }

            return $rules;
        });

        $this->setCallback('itemAfterValidation', function (array $validItem, array $rawItem) use ($branchId, &$existingMap, &$hasReceivablesMap, &$hasActiveContractMap) {
            $this->model->where($this->getDirectRelationCriteria($branchId));

            $itemId   = $validItem['id'];
            $existing = $existingMap[$itemId] ?? null;
            if ($existing === null) {
                return;
            }

            if (array_key_exists('client_id', $validItem)
                && (string) $validItem['client_id'] !== (string) $existing['client_id']
                && isset($hasReceivablesMap[$itemId])
            ) {
                return [
                    'status'   => 409,
                    'error'    => 'FINANCING_CLIENT_LOCKED',
                    'messages' => ['client_id' => 'No se puede cambiar el cliente de un financiamiento con cuentas por cobrar generadas.'],
                ];
            }

            if (array_key_exists('contract_type_id', $validItem)
                && (string) ($validItem['contract_type_id'] ?? '') !== (string) ($existing['contract_type_id'] ?? '')
                && isset($hasActiveContractMap[$itemId])
            ) {
                return [
                    'status'   => 409,
                    'error'    => 'FINANCING_HAS_ACTIVE_CONTRACT',
                    'messages' => ['contract_type_id' => 'No se puede cambiar el tipo de contrato mientras exista un contrato activo o firmado.'],
                ];
            }

            $effTotal      = array_key_exists('total_amount', $validItem) ? $validItem['total_amount'] : $existing['total_amount'];
            $effInitial    = array_key_exists('initial_amount', $validItem) ? $validItem['initial_amount'] : $existing['initial_amount'];
            $effCount      = array_key_exists('installment_count', $validItem) ? $validItem['installment_count'] : $existing['installment_count'];
            $effInstAmount = array_key_exists('installment_amount', $validItem) ? $validItem['installment_amount'] : $existing['installment_amount'];

            $financialError = $this->validateFinancialInvariants($effTotal, $effInitial, $effCount, $effInstAmount);
            if ($financialError !== null) {
                return $financialError;
            }

            $effInitDate = array_key_exists('initial_due_date', $validItem) ? $validItem['initial_due_date'] : $existing['initial_due_date'];
            $effBaseDate = array_key_exists('frequency_base_date', $validItem) ? $validItem['frequency_base_date'] : $existing['frequency_base_date'];
            if (!empty($effInitDate) && strcmp((string) $effInitDate, (string) $effBaseDate) > 0) {
                return [
                    'status'   => 400,
                    'error'    => 'FAIL_VALIDATION',
                    'messages' => ['initial_due_date' => lang('Validation.date_less_than_equal_to', ['param' => 'frequency_base_date'])],
                ];
            }

            $effContractTypeId = array_key_exists('contract_type_id', $validItem)
                ? $validItem['contract_type_id']
                : $existing['contract_type_id'];

            if (empty($effContractTypeId)) {
                if (array_key_exists('contract_type_id', $validItem) || (array_key_exists('benefit_metadata', $validItem) && $validItem['benefit_metadata'] !== null)) {
                    $validItem['benefit_metadata'] = null;
                    return $validItem;
                }
                return;
            }

            if (array_key_exists('benefit_metadata', $rawItem)) {
                $metadata = $this->getMetadata($effContractTypeId);
                if ($metadata !== null) {
                    return $metadata->process($validItem, $itemId);
                }
            }
        });

        return $this->baseUpdate($id);
    }

    public function delete($branchId = null, $id = null): Response {
        $branch = $this->getAncestorRelation($branchId);
        if ($branch instanceof Response) {
            return $branch;
        }

        $deletedIds = [];

        $this->setCallback('before', function (array $input) use ($branchId, $id) {
            $isBatch = !is_numeric($id);
            $ids     = $isBatch ? array_column($input, 'id') : [$id];
            $ids     = array_values(array_unique(array_filter($ids, 'is_numeric')));

            if (empty($ids)) {
                return;
            }

            $blocked = $this->model->db->table('acc_receivable r')
                ->select('r.origin_id')
                ->join('acc_receipt_item ri', 'ri.receivable_id = r.id AND ri.deleted_at IS NULL', 'left')
                ->where('r.branch_id', $branchId)
                ->where('r.origin_type', '1')
                ->whereIn('r.origin_id', $ids)
                ->groupStart()
                    ->where('r.paid_amount >', 0)
                    ->orWhereIn('r.status', ['1', '2'])
                    ->orWhere('ri.id IS NOT NULL')
                ->groupEnd()
                ->limit(1)
                ->get()
                ->getRowArray();

            if ($blocked) {
                return $this->fail(
                    'No se puede eliminar el financiamiento porque posee cobros o recibos asociados.',
                    409,
                    'FINANCING_HAS_PAYMENTS'
                );
            }
        });

        $this->setCallback('itemAfterValidation', function () use ($branchId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId));
        });

        $this->setCallback('itemSuccess', function (int|string $itemId) use (&$deletedIds): void {
            $deletedIds[] = $itemId;
        });

        $this->setCallback('after', function (array $response, array $actionConfig) use ($branchId, &$deletedIds): void {
            if (empty($deletedIds)) {
                return;
            }

            $builder = $this->model->db->table('acc_receivable')
                ->where('branch_id', $branchId)
                ->where('origin_type', '1')
                ->whereIn('origin_id', $deletedIds);

            if (!empty($actionConfig['inTrash'])) {
                $builder->delete();
            } else {
                $builder->where('deleted_at IS NULL')
                    ->update(['deleted_at' => $actionConfig['timestamp']]);
            }
        });

        return $this->baseDelete($id);
    }

    public function restore($branchId = null, $id = null): Response {
        $branch = $this->getAncestorRelation($branchId);
        if ($branch instanceof Response) {
            return $branch;
        }

        $deletedAtById = [];
        $restoredIds   = [];

        $this->setCallback('before', function (array $input) use ($branchId, $id, &$deletedAtById): void {
            $isBatch = !is_numeric($id);
            $ids     = $isBatch ? array_column($input, 'id') : [$id];
            $ids     = array_values(array_unique(array_filter($ids, 'is_numeric')));

            if (empty($ids)) {
                return;
            }

            $rows = $this->model
                ->withDeleted()
                ->select('id, deleted_at')
                ->where('branch_id', $branchId)
                ->where('deleted_at IS NOT NULL')
                ->whereIn('id', $ids)
                ->findAll();

            foreach ($rows as $row) {
                $deletedAtById[$row['id']] = $row['deleted_at'];
            }
        });

        $this->setCallback('itemAfterValidation', function () use ($branchId): void {
            $this->model->where($this->getDirectRelationCriteria($branchId));
        });

        $this->setCallback('itemSuccess', function (int|string $itemId) use (&$restoredIds): void {
            $restoredIds[] = $itemId;
        });

        $this->setCallback('after', function () use ($branchId, &$deletedAtById, &$restoredIds): void {
            if (empty($restoredIds)) {
                return;
            }

            $idsByTimestamp = [];
            foreach ($restoredIds as $rid) {
                if (!empty($deletedAtById[$rid])) {
                    $idsByTimestamp[$deletedAtById[$rid]][] = $rid;
                }
            }

            foreach ($idsByTimestamp as $deletedAt => $groupIds) {
                $this->model->db->table('acc_receivable')
                    ->where('branch_id', $branchId)
                    ->where('origin_type', '1')
                    ->whereIn('origin_id', $groupIds)
                    ->where('deleted_at', $deletedAt)
                    ->update(['deleted_at' => null]);
            }
        });

        return $this->baseRestore($id);
    }

    /**
     * Genera o regenera la cartera de cuentas por cobrar (inicial y cuotas) del financiamiento.
     * POST v1/branches/{branch_id}/financing/{financing_id}/generate-receivables
     */
    public function generateReceivables($branchId = null, $id = null): Response {
        if (!is_numeric($id)) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        $branch = $this->getAncestorRelation($branchId);
        if ($branch instanceof Response) {
            return $branch;
        }

        $service = new Financing($this->model->db);
        $result  = $service->generateReceivables((int) $branchId, (int) $id);

        $status = $result['status'] ?? 200;
        return $this->response->setStatusCode($status)->setJSON($result);
    }

    /**
     * Cartola / Estado de cuenta individual del financiamiento.
     * GET v1/branches/{branch_id}/financing/{financing_id}/reports/statement
     */
    public function statement($branchId = null, $id = null): Response {
        if (!is_numeric($id)) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        $branch = $this->getAncestorRelation($branchId, false);
        if ($branch instanceof Response) {
            return $branch;
        }

        $service   = new Financing($this->model->db);
        $statement = $service->getStatement((int) $branchId, (int) $id);

        if ($statement === null) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        return $this->respond([
            'status' => 200,
            'data'   => $statement,
        ], 200);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------

    private function _read($branchId, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        $branch = $this->getAncestorRelation($branchId, false);
        if ($branch instanceof Response) {
            return $branch;
        }

        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($branchId, true));
        $this->setConfig('read:fieldsCalculated', $this->schema::FIELDS_CALCULATED);
        $this->setCallback('read:fieldsCalculated', $this->schema::fieldsCalculatedCallback(...));

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria(int|string $branchId, bool $isRead = false): array {
        if ($isRead) {
            return [['branch_id', $branchId]];
        }
        return ['branch_id' => $branchId];
    }

    private function getAncestorRelation(int|string $branchId, bool $addIsDisabled = true): array|Response {
        $builder = $this->model->db->table('glb_branch')
            ->select('id, name')
            ->where('id', $branchId)
            ->where('deleted_at IS NULL');

        if ($addIsDisabled) {
            $builder->where('is_disabled', '0');
        }

        $branch = $builder->limit(1)->get()->getRowArray();

        if (!$branch) {
            return $this->fail(lang('CrudTraits.read.record_not_found'), 404, 'RECORD_NOT_FOUND');
        }

        return $branch;
    }

    private function getMetadata(int|string $contractTypeId): ?Metadata {
        $contractTypes = model(ContractTypeModel::class)->getByCache();
        $schemaId      = $contractTypes[(int) $contractTypeId]['metadata_schema_id'] ?? null;

        return $schemaId !== null ? new Metadata((int) $schemaId) : null;
    }

    private function normalizeNullableFields(array $item): array {
        foreach (['contract_type_id', 'initial_due_date', 'awarded_at', 'benefit_metadata'] as $field) {
            if (array_key_exists($field, $item) && $item[$field] === '') {
                $item[$field] = null;
            }
        }
        return $item;
    }

    private function calculateInstallmentAmount(mixed $total, mixed $initial, mixed $count): ?float {
        if (!is_numeric($total) || !is_numeric($initial) || !is_numeric($count)) {
            return null;
        }

        $countVal = (int) $count;
        $balance  = (float) $total - (float) $initial;
        if ($countVal <= 0 || $balance <= 0.0) {
            return null;
        }

        $amount = round($balance / $countVal, 2);
        return $amount > 0.0 ? $amount : null;
    }

    private function validateFinancialInvariants(mixed $total, mixed $initial, mixed $count, mixed $installmentAmount): ?array {
        $totalVal   = (float) $total;
        $initialVal = (float) $initial;
        $countVal   = (int) $count;

        if ($initialVal >= $totalVal) {
            return [
                'status'   => 400,
                'error'    => 'FAIL_VALIDATION',
                'messages' => ['initial_amount' => 'El monto inicial debe ser menor al total a pagar.'],
            ];
        }

        if ($installmentAmount === null || $installmentAmount === '' || (float) $installmentAmount <= 0.0) {
            return [
                'status'   => 400,
                'error'    => 'FAIL_VALIDATION',
                'messages' => ['installment_amount' => 'El monto de la cuota debe ser mayor a cero.'],
            ];
        }

        $instVal         = (float) $installmentAmount;
        $financedBalance = round($totalVal - $initialVal, 2);
        $lastInstallment = round($financedBalance - (($countVal - 1) * $instVal), 2);

        if ($instVal > $financedBalance || $lastInstallment <= 0.0) {
            return [
                'status'   => 400,
                'error'    => 'FAIL_VALIDATION',
                'messages' => ['installment_amount' => 'El monto de la cuota supera el saldo financiado disponible para el total de cuotas.'],
            ];
        }

        return null;
    }
}
