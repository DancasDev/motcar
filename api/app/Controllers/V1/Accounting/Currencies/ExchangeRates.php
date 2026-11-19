<?php

namespace App\Controllers\V1\Accounting\Currencies;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Controllers\Traits\CrudTraits;
use App\Libraries\Validators\CompoundKeysValidator;
use App\Models\Accounting\CurrencyModel;

/**
 * Tasas de cambio de una moneda (acc_exchange_rate).
 *
 * Sub-recurso anidado bajo monedas (v1/accounting/currencies/{currency_id}/exchange-rates).
 *
 * - current: tasa de cambio más reciente obtenida de la caché de la moneda.
 * - index/show: lectura filtrada fijamente por currency_id.
 * - create: valida unicidad de fecha e inyecta currency_id. Purga la caché de monedas si hubo inserción.
 * - update/delete/restore: mutaciones con purga condicional de la caché de monedas.
 */
class ExchangeRates extends BaseController {
    use CrudTraits;

    protected $modelName  = 'App\Models\Accounting\ExchangeRateModel';
    protected $schemaName = 'App\Schemas\Accounting\ExchangeRateSchema';

    private ?CompoundKeysValidator $compoundKeysValidator = null;

    public function current($currencyId = null): Response {
        $currencies = model(CurrencyModel::class)->getByCache();
        if ($currencyId === null || !isset($currencies[$currencyId])) {
            return $this->failNotFound(lang('CrudTraits.read.record_not_found'));
        }

        $currency = $currencies[$currencyId];

        return $this->respond([
            'status' => 200,
            'data'   => [
                'currency_id' => $currency['id'],
                'value'       => $currency['rate_value'],
                'date'        => $currency['rate_date'],
            ],
        ]);
    }

    public function create($currencyId = null): Response {
        $this->setCallback('itemSetValues', function (array $item) use ($currencyId): array {
            $item['currency_id'] = $currencyId;
            return $item;
        });

        $this->setCallback('itemAfterValidation', function (array $item) {
            $result = $this->validationOfCompositeKeys($item);
            if ($result !== true) {
                return $result;
            }
        });

        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseCreate();
    }

    public function index($currencyId = null): Response {
        return $this->_read($currencyId);
    }

    public function show($currencyId = null, $id = null): Response {
        return $this->_read($currencyId, $id);
    }

    public function update($currencyId = null, $id = null): Response {
        $this->setCallback('itemSetValues', function (array $item): array {
            unset($item['currency_id']);
            return $item;
        });

        $this->setCallback('itemAfterValidation', function (array $item) use ($currencyId) {
            $this->model->where($this->getDirectRelationCriteria($currencyId));

            if (array_key_exists('date', $item)) {
                $result = $this->validationOfCompositeKeys(array_merge($item, ['currency_id' => $currencyId]));
                if ($result !== true) {
                    return $result;
                }
            }
        });

        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseUpdate($id);
    }

    public function delete($currencyId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function (array $item) use ($currencyId): void {
            $this->model->where($this->getDirectRelationCriteria($currencyId));
        });

        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseDelete($id);
    }

    public function restore($currencyId = null, $id = null): Response {
        $this->setCallback('itemAfterValidation', function (array $item) use ($currencyId): void {
            $this->model->where($this->getDirectRelationCriteria($currencyId));
        });

        $this->setCallback('after', function (array $response): void {
            if ($response['affected'] > 0) {
                $this->purgeCache();
            }
        });

        return $this->baseRestore($id);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function _read($currencyId = null, $id = null, bool $returnArray = false, ?int $limit = null): Response|array {
        if (!empty($limit)) {
            $this->setConfig('limit', $limit);
        }

        $this->setConfig('read:filtersDefault', $this->getDirectRelationCriteria($currencyId, true));

        return $this->baseRead($id, $returnArray);
    }

    private function getDirectRelationCriteria($currencyId, bool $isRead = false): array {
        if ($isRead) {
            return [['currency_id', $currencyId]];
        }

        return ['currency_id' => $currencyId];
    }

    private function validationOfCompositeKeys(array $item): bool|array {
        if ($this->compoundKeysValidator === null) {
            $this->compoundKeysValidator = new CompoundKeysValidator();
        }

        if (!$this->compoundKeysValidator->validate(
            item: $item,
            table: $this->model->table,
            fields: ['currency_id', 'date'],
            pkField: $this->model->primaryKey
        )) {
            return [
                'status'   => 400,
                'error'    => 'FAIL_VALIDATION',
                'messages' => ['date' => $this->compoundKeysValidator->getError()],
            ];
        }

        return true;
    }

    private function purgeCache(): void {
        model(CurrencyModel::class)->removeByCache();
    }
}
