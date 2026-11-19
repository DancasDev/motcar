<?php

namespace App\Database\Seeds\Accounting;

use CodeIgniter\Database\Seeder;

class CurrencySeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('acc_currency');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            $existing = $builder->select('id')->where('code', $row['code'])->limit(1)->get()->getRow();

            if ($existing) {
                $row['updated_at'] = $time;
                $builder->where('id', $existing->id)->update($row);
                $updated++;
            } else {
                $row['created_at'] = $time;
                $builder->insert($row);
                $inserted++;
            }
        }

        echo "  Monedas insertadas: {$inserted}, actualizadas: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [];
    }

    private function getCustomData(): array {
        return [
            [
                'code'        => 'USD',
                'name'        => 'Dólar Estadounidense',
                'symbol'      => '$',
                'is_disabled' => '0',
            ],
            [
                'code'        => 'VES',
                'name'        => 'Bolívar Digital',
                'symbol'      => 'Bs.',
                'is_disabled' => '0',
            ],
        ];
    }
}
