<?php

namespace App\Libraries\Accounting;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * Componente del dominio contable para la gestión y validación de medios de pago (acc_receipt_payment).
 *
 * Responsabilidades:
 * - Normalización de payload de entrada (mapeo polimórfico de cuentas bancarias y créditos).
 * - Resolución de tasas de cambio según fecha y origen.
 * - Validación de disponibilidad de saldos a favor y prevención de duplicados en el comprobante.
 * - Conversión y cálculo consolidado a moneda base (USD).
 */
class Payment {
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null) {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Normaliza los campos de entrada antes de la validación del modelo.
     */
    public function normalizeInput(array $item, int $receiptId): array {
        $item['receipt_id']   = $receiptId;
        $item['payment_date'] = !empty($item['payment_date']) ? $item['payment_date'] : date('Y-m-d');

        if (isset($item['payment_account_id']) && !isset($item['origin_id'])) {
            $item['origin_type'] = '0';
            $item['origin_id']   = $item['payment_account_id'];
        }

        if (!isset($item['origin_type'])) {
            $item['origin_type'] = '0';
        }

        return $item;
    }

    /**
     * Resuelve la tasa de cambio y atributos del origen del pago (cuenta bancaria o saldo a favor).
     */
    public function resolveOriginRate(string $originType, int $originId, string $paymentDate, ?int $clientId = null): array {
        if ($originType === '1') {
            $credit = $this->db->table('acc_client_credit as cc')
                ->select('cc.*, c.code as currency_code')
                ->join('acc_currency as c', 'cc.currency_id = c.id', 'left')
                ->where('cc.id', $originId)
                ->where('cc.deleted_at IS NULL')
                ->get()
                ->getRowArray();

            if (!$credit) {
                return [
                    'status'  => 400,
                    'error'   => 'CREDIT_NOT_FOUND',
                    'message' => lang('Receipt.credit_not_found'),
                ];
            }

            if ($clientId !== null && (int) $credit['client_id'] !== $clientId) {
                return [
                    'status'  => 400,
                    'error'   => 'CREDIT_CLIENT_MISMATCH',
                    'message' => lang('Receipt.credit_client_mismatch'),
                ];
            }

            if ($credit['status'] !== '0' || (float) $credit['available_amount'] <= 0.0) {
                return [
                    'status'  => 400,
                    'error'   => 'CREDIT_NOT_AVAILABLE',
                    'message' => lang('Receipt.credit_not_available'),
                ];
            }

            $currencyId       = (int) $credit['currency_id'];
            $currencyCode     = $credit['currency_code'] ?? 'USD';
            $availableAmount  = (float) $credit['available_amount'];
            $paymentAccountId = (int) $credit['payment_account_id'];
        } else {
            $account = $this->db->table('acc_payment_account as pa')
                ->select('pa.currency_id, c.code as currency_code')
                ->join('acc_currency as c', 'pa.currency_id = c.id', 'left')
                ->where('pa.id', $originId)
                ->where('pa.deleted_at IS NULL')
                ->where('pa.is_disabled', '0')
                ->get()
                ->getRowArray();

            if (!$account) {
                return [
                    'status'  => 400,
                    'error'   => 'PAYMENT_ACCOUNT_NOT_FOUND',
                    'message' => lang('Receipt.payment_origin_not_found'),
                ];
            }

            $currencyId       = (int) $account['currency_id'];
            $currencyCode     = $account['currency_code'] ?? 'USD';
            $availableAmount  = null;
            $paymentAccountId = $originId;
        }

        if ($originType === '1') {
            $frozenRate = (float) ($credit['exchange_rate'] ?? 1.0);
            if ($frozenRate <= 0.0) {
                $frozenRate = 1.0;
            }

            return [
                'rate'               => $frozenRate,
                'currency_id'        => $currencyId,
                'code'               => $currencyCode,
                'available_amount'   => $availableAmount,
                'payment_account_id' => $paymentAccountId,
            ];
        }

        if ($currencyCode === 'USD' || $currencyId === 1) {
            return [
                'rate'               => 1.0,
                'currency_id'        => $currencyId,
                'code'               => $currencyCode,
                'available_amount'   => $availableAmount,
                'payment_account_id' => $paymentAccountId,
            ];
        }

        $rateRow = $this->db->table('acc_exchange_rate')
            ->where('currency_id', $currencyId)
            ->where('date <=', $paymentDate)
            ->where('deleted_at IS NULL')
            ->orderBy('date', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (!$rateRow) {
            return [
                'status'  => 400,
                'error'   => 'EXCHANGE_RATE_NOT_FOUND',
                'message' => lang('Receipt.exchange_rate_not_found', [$currencyCode, $paymentDate]),
            ];
        }

        return [
            'rate'               => (float) $rateRow['value'],
            'currency_id'        => $currencyId,
            'code'               => $currencyCode,
            'available_amount'   => $availableAmount,
            'payment_account_id' => $paymentAccountId,
        ];
    }

    /**
     * Valida reglas de negocio y enriquece el ítem de pago con tasas y equivalencia USD.
     */
    public function prepare(array $item, int $receiptId, array $receipt, ?int $currentId = null): array {
        if ($currentId !== null && (!isset($item['origin_id']) || !isset($item['amount']))) {
            $existing = $this->db->table('acc_receipt_payment')
                ->where('id', $currentId)
                ->where('receipt_id', $receiptId)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRowArray();

            if ($existing) {
                if (!isset($item['amount'])) {
                    $item['amount'] = $existing['amount'];
                }
                if (!isset($item['origin_type'])) {
                    $item['origin_type'] = $existing['origin_type'];
                }
                if (!isset($item['origin_id'])) {
                    $item['origin_id'] = $existing['origin_id'];
                }
                if (!isset($item['payment_date'])) {
                    $item['payment_date'] = $existing['payment_date'];
                }
            }
        }

        $amount = (float) ($item['amount'] ?? 0.0);
        if ($amount <= 0.0) {
            return [
                'status'   => 400,
                'error'    => 'INVALID_AMOUNT',
                'messages' => ['amount' => 'El monto debe ser mayor a 0'],
            ];
        }

        $originType  = (string) ($item['origin_type'] ?? '0');
        $originId    = (int) ($item['origin_id'] ?? 0);
        $paymentDate = (string) ($item['payment_date'] ?? date('Y-m-d'));
        $clientId    = (int) ($receipt['client_id'] ?? 0);

        if ($originType === '1') {
            $builder = $this->db->table('acc_receipt_payment')
                ->where('receipt_id', $receiptId)
                ->where('origin_type', '1')
                ->where('origin_id', $originId)
                ->where('deleted_at IS NULL');

            if ($currentId !== null) {
                $builder->where('id !=', $currentId);
            }

            if ($builder->countAllResults() > 0) {
                return [
                    'status'   => 400,
                    'error'    => 'CREDIT_DUPLICATE_IN_RECEIPT',
                    'messages' => ['origin_id' => lang('Receipt.credit_duplicate_in_receipt')],
                ];
            }
        }

        $rateInfo = $this->resolveOriginRate($originType, $originId, $paymentDate, $clientId);
        if (isset($rateInfo['error'])) {
            return [
                'status'   => $rateInfo['status'] ?? 400,
                'error'    => $rateInfo['error'],
                'messages' => ['origin_id' => $rateInfo['message']],
            ];
        }

        if ($originType === '1') {
            $available = (float) ($rateInfo['available_amount'] ?? 0.0);
            if ($amount > $available) {
                return [
                    'status'   => 400,
                    'error'    => 'CREDIT_INSUFFICIENT_BALANCE',
                    'messages' => ['amount' => lang('Receipt.credit_insufficient_balance', [$amount, $available])],
                ];
            }
        }

        $rate = (float) $rateInfo['rate'];
        $item['receipt_id']    = $receiptId;
        $item['origin_type']   = $originType;
        $item['origin_id']     = $originId;
        $item['payment_date']  = $paymentDate;
        $item['exchange_rate'] = $rate;
        $item['amount_usd']    = round($amount / $rate, 2);

        return $item;
    }

    /**
     * Calcula la equivalencia consolidada en USD de una lista de pagos.
     */
    public function calculateUsdTotal(array $payments): float {
        $totalUsd = 0.0;
        foreach ($payments as $p) {
            $rate = (float) ($p['exchange_rate'] ?? 1.0);
            if ($rate <= 0) {
                $rate = 1.0;
            }
            $amount = (float) ($p['amount'] ?? 0.0);
            $totalUsd += round($amount / $rate, 2);
        }
        return round($totalUsd, 2);
    }
}
