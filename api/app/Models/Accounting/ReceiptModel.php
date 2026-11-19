<?php

namespace App\Models\Accounting;

use App\Models\BaseModel;

class ReceiptModel extends BaseModel {
    protected $table         = 'acc_receipt';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'branch_id',
        'client_id',
        'collector_id',
        'code',
        'date',
        'notes',
        'cancellation_reason',
        'status',
    ];

    protected $validationRules = [
        'branch_id'           => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_branch.id,is_disabled:0;deleted_at:IS_NULL]',
        'client_id'           => 'required|is_natural_no_zero|is_not_unique_multi_condition[glb_person.id,is_disabled:0;deleted_at:IS_NULL]',
        'collector_id'        => 'permit_empty|is_natural_no_zero|is_not_unique_multi_condition[gac_user.id,is_disabled:0;deleted_at:IS_NULL]',
        'code'                => 'required|string|max_length[40]|is_unique[acc_receipt.code,id,{id}]',
        'date'                => 'required|valid_date',
        'notes'               => 'permit_empty|string',
        'cancellation_reason' => 'permit_empty|string|max_length[255]',
        'status'              => 'permit_empty|in_list[0,1,2]',
    ];

    private const DETAIL_CACHE_PREFIX = 'acc_receipt_detail_';
    private const DETAIL_CACHE_TTL    = 300; // 5 minutos

    /**
     * Obtiene el detalle estructurado y consolidado del recibo desde caché (TTL 5 min).
     * En miss o $reset = true, consulta la base de datos y almacena en caché.
     * Si no existe o no pertenece a la sucursal, almacena [] para evitar consultas recurrentes (anti-stampede).
     *
     * @param int  $branchId  ID de la sucursal.
     * @param int  $receiptId ID del recibo.
     * @param bool $reset     Fuerza la invalidación y recarga desde la base de datos.
     * @return array
     */
    public function getDetailByCache(int $branchId, int $receiptId, bool $reset = false): array {
        $cache    = service('cache');
        $cacheKey = self::DETAIL_CACHE_PREFIX . $branchId . '_' . $receiptId;

        if ($reset) {
            $cache->delete($cacheKey);
        }

        $detail = $cache->get($cacheKey);
        if (!is_array($detail)) {
            $receipt = $this->db->table('acc_receipt as r')
                ->select('r.*, b.name as branch_name, p.first_name as client_first_name, p.last_name as client_last_name, p.identification_type as client_identification_type, p.identification_value as client_identification_value, p.phone_primary as client_phone, p.address as client_address, p.city as client_city, u.first_name as collector_first_name, u.last_name as collector_last_name')
                ->join('glb_branch as b', 'r.branch_id = b.id', 'left')
                ->join('glb_person as p', 'r.client_id = p.id', 'left')
                ->join('gac_user as u', 'r.collector_id = u.id', 'left')
                ->where('r.id', $receiptId)
                ->where('r.branch_id', $branchId)
                ->where('r.deleted_at IS NULL')
                ->limit(1)
                ->get()
                ->getRowArray();

            if (!$receipt) {
                $cache->save($cacheKey, [], self::DETAIL_CACHE_TTL);
                return [];
            }

            $items = $this->db->table('acc_receipt_item as ri')
                ->select('ri.*, r.description as receivable_description, r.total_amount as receivable_total_amount, r.paid_amount as receivable_paid_amount, r.status as receivable_status')
                ->join('acc_receivable as r', 'ri.receivable_id = r.id', 'left')
                ->where('ri.receipt_id', $receiptId)
                ->where('ri.deleted_at IS NULL')
                ->get()
                ->getResultArray();

            foreach ($items as &$it) {
                $type    = (string) ($it['type'] ?? '0');
                $rawDesc = $it['receivable_description'] ?? 'Concepto';

                $suffix = match ($type) {
                    '2'     => ' (abono)',
                    '3'     => ' (restante)',
                    default => '',
                };

                $it['display_description'] = $rawDesc . $suffix;
            }
            unset($it);

            $payments = $this->db->table('acc_receipt_payment as rp')
                ->select("rp.*, 
                         CASE WHEN rp.origin_type = '0' THEN pa.name ELSE 'Saldo a Favor' END as payment_account_name,
                         CASE WHEN rp.origin_type = '0' THEN pac.name ELSE 'Crédito Interno' END as payment_category_name,
                         COALESCE(c1.code, c2.code) as currency_code, 
                         COALESCE(c1.symbol, c2.symbol) as currency_symbol")
                ->join('acc_payment_account as pa', "rp.origin_id = pa.id AND rp.origin_type = '0'", 'left')
                ->join('acc_payment_account_category as pac', 'pa.category_id = pac.id', 'left')
                ->join('acc_currency as c1', 'pa.currency_id = c1.id', 'left')
                ->join('acc_client_credit as cc', "rp.origin_id = cc.id AND rp.origin_type = '1'", 'left')
                ->join('acc_currency as c2', 'cc.currency_id = c2.id', 'left')
                ->where('rp.receipt_id', $receiptId)
                ->where('rp.deleted_at IS NULL')
                ->get()
                ->getResultArray();

            $credits = $this->db->table('acc_client_credit as cc')
                ->select('cc.*, c.code as currency_code')
                ->join('acc_currency as c', 'cc.currency_id = c.id', 'left')
                ->where('cc.receipt_id', $receiptId)
                ->where('cc.deleted_at IS NULL')
                ->get()
                ->getResultArray();

            $totalPaymentsUsd = 0.0;
            foreach ($payments as $p) {
                $rate = (float) ($p['exchange_rate'] ?? 1.0);
                if ($rate <= 0) {
                    $rate = 1.0;
                }
                $totalPaymentsUsd += round(((float) ($p['amount'] ?? 0.0)) / $rate, 2);
            }

            $totalItemsUsd = 0.0;
            foreach ($items as $it) {
                $totalItemsUsd += (float) ($it['amount'] ?? 0.0);
            }

            $totalCreditsUsd = 0.0;
            foreach ($credits as $cr) {
                $rate = (float) ($cr['exchange_rate'] ?? 1.0);
                if ($rate <= 0) {
                    $rate = 1.0;
                }
                $totalCreditsUsd += round(((float) ($cr['amount'] ?? 0.0)) / $rate, 2);
            }

            $totalPaymentsUsd = round($totalPaymentsUsd, 2);
            $totalItemsUsd    = round($totalItemsUsd, 2);
            $totalCreditsUsd  = round($totalCreditsUsd, 2);

            $detail = $receipt;
            $detail['items']    = $items;
            $detail['payments'] = $payments;
            $detail['credits']  = $credits;
            $detail['totals']   = [
                'payments_usd' => $totalPaymentsUsd,
                'items_usd'    => $totalItemsUsd,
                'credit_usd'   => $totalCreditsUsd,
                'balance_usd'  => round($totalPaymentsUsd - $totalItemsUsd, 2),
            ];

            $cache->save($cacheKey, $detail, self::DETAIL_CACHE_TTL);
        }

        return $detail;
    }

    /**
     * Invalida la entrada en caché del detalle de un recibo.
     *
     * @param int $branchId  ID de la sucursal.
     * @param int $receiptId ID del recibo.
     */
    public function removeDetailByCache(int $branchId, int $receiptId): void {
        service('cache')->delete(self::DETAIL_CACHE_PREFIX . $branchId . '_' . $receiptId);
    }
}
