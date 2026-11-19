<?php

namespace App\Database\Seeds\Global;

use CodeIgniter\Database\Seeder;

class LanguageSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('glb_language');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());
        foreach ($list as $code => $names) {
            $name = json_encode($names, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $existing = $builder->select('id')
                ->where('code', $code)
                ->limit(1)
                ->get()
                ->getRow();

            if ($existing) {
                $builder->where('id', $existing->id)->update([
                    'name'       => $name,
                    'updated_at' => $time,
                ]);
                $updated++;
            } else {
                $builder->insert([
                    'code'       => $code,
                    'name'       => $name,
                    'created_at' => $time,
                ]);
                $inserted++;
            }
        }

        echo "  Idiomas insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [
            'es' => ['es' => 'Español', 'en' => 'Spanish'],
            'en' => ['es' => 'Inglés', 'en' => 'English'],
        ];
    }

    private function getCustomData(): array {
        return [];
    }
}
