<?php

namespace App\Models\Accounting;

use App\Models\BaseModel;

class CurrencyModel extends BaseModel {
    protected $table         = 'acc_currency';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'name',
        'code',
        'symbol',
        'is_disabled',
    ];

    protected $validationRules = [
        'name'        => 'required|string|max_length[60]',
        'code'        => 'required|alpha|max_length[3]|is_unique[acc_currency.code,id,{id}]',
        'symbol'      => 'required|string|max_length[10]',
        'is_disabled' => 'permit_empty|in_list[0,1]',
    ];

    private const CURRENCIES_CACHE_KEY = 'db_acc_curr_list';
    private const CURRENCIES_CACHE_TTL = 3600;

    /**
     * Obtiene las monedas desde la caché del sistema indexadas por ID con su última tasa.
     * En miss o $reset = true, consulta directamente la base de datos y almacena en caché.
     * Si no hay registros ([]), se almacena igualmente para evitar consultas recurrentes (anti-stampede).
     *
     * @param bool $reset Fuerza la recarga invalidando la caché existente.
     * @return array<int, array<string, mixed>>
     */
    public function getByCache(bool $reset = false): array {
        $cache = service('cache');
        $key   = self::CURRENCIES_CACHE_KEY;

        if ($reset) {
            $cache->delete($key);
        }

        $data = $cache->get($key);
        if (!is_array($data)) {
            $records = $this->db->table('acc_currency c')
                ->select('c.id, c.code, c.name, c.symbol, latest.value AS rate_value, latest.date AS rate_date')
                ->join(
                    '(SELECT er.currency_id, er.value, er.date 
                      FROM acc_exchange_rate er 
                      INNER JOIN (
                          SELECT currency_id, MAX(date) AS max_date 
                          FROM acc_exchange_rate 
                          WHERE deleted_at IS NULL 
                          GROUP BY currency_id
                      ) max_er ON er.currency_id = max_er.currency_id AND er.date = max_er.max_date 
                      WHERE er.deleted_at IS NULL) latest',
                    'latest.currency_id = c.id',
                    'left'
                )
                ->where('c.deleted_at IS NULL')
                ->where('c.is_disabled', '0')
                ->get()
                ->getResultArray();

            $data = [];
            foreach ($records as $row) {
                $id = (int) $row['id'];
                $data[$id] = [
                    'id'         => $id,
                    'code'       => $row['code'],
                    'name'       => $row['name'],
                    'symbol'     => $row['symbol'],
                    'rate_value' => $row['rate_value'],
                    'rate_date'  => $row['rate_date'],
                ];
            }

            $cache->save($key, $data, self::CURRENCIES_CACHE_TTL);
        }

        return $data;
    }

    /**
     * Invalida la caché de monedas.
     */
    public function removeByCache(): void {
        service('cache')->delete(self::CURRENCIES_CACHE_KEY);
    }
}
