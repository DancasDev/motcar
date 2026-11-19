<?php

namespace App\Database\Seeds\Global;

use CodeIgniter\Database\Seeder;

class DocumentTypeSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('glb_document_type');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            $row['name'] = json_encode($row['name'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $row['description'] = json_encode($row['description'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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

        echo "  Formatos de pagina insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [
            [
                'code'        => 'a4',
                'name'        => ['es' => 'A4', 'en' => 'A4'],
                'description' => ['es' => 'Formato de página A4.', 'en' => 'A4 page format.'],
                'width'       => 595.28,
                'height'      => 841.89,
                'is_disabled' => '0',
            ],
            [
                'code'        => 'letter',
                'name'        => ['es' => 'Carta', 'en' => 'Letter'],
                'description' => ['es' => 'Formato de página Carta (Letter).', 'en' => 'Letter page format.'],
                'width'       => 612.00,
                'height'      => 792.00,
                'is_disabled' => '0',
            ],
        ];
    }

    private function getCustomData(): array {
        return [];
    }
}
