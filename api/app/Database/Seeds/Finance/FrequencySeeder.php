<?php

namespace App\Database\Seeds\Finance;

use CodeIgniter\Database\Seeder;

class FrequencySeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('fnc_frequency');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            $row['name'] = json_encode($row['name'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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

        echo "  Frecuencias insertadas: {$inserted}, actualizadas: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [];
    }

    private function getCustomData(): array {
        return [
            [
                'code'        => 'weekly',
                'name'        => ['es' => 'Semanal', 'en' => 'Weekly'],
                'is_disabled' => '0',
            ],
            [
                'code'        => 'biweekly',
                'name'        => ['es' => 'Quincenal', 'en' => 'Biweekly'],
                'is_disabled' => '0',
            ],
            [
                'code'        => 'monthly',
                'name'        => ['es' => 'Mensual', 'en' => 'Monthly'],
                'is_disabled' => '0',
            ],
        ];
    }
}
