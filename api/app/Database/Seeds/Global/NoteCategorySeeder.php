<?php

namespace App\Database\Seeds\Global;

use CodeIgniter\Database\Seeder;

class NoteCategorySeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('glb_note_category');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            $existing = $builder->select('id')->where('name', $row['name'])->limit(1)->get()->getRow();

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

        echo "  Categorías de notas insertadas: {$inserted}, actualizadas: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [];
    }

    private function getCustomData(): array {
        return [
            [
                'name'        => 'Seguimiento de Cobranza',
                'description' => 'Anotaciones de gestión y recordatorios de pago',
                'color'       => '#ffc107',
                'is_disabled' => '0',
            ],
            [
                'name'        => 'Compromiso de Pago',
                'description' => 'Acuerdos verbales o suscritos de fecha futura de pago',
                'color'       => '#17a2b8',
                'is_disabled' => '0',
            ],
            [
                'name'        => 'Notificación de Mora',
                'description' => 'Alertas y notificaciones enviadas por cuotas vencidas',
                'color'       => '#dc3545',
                'is_disabled' => '0',
            ],
            [
                'name'        => 'Adjudicación de Casco / Bien',
                'description' => 'Registro de entrega o adjudicación de bien al participante',
                'color'       => '#28a745',
                'is_disabled' => '0',
            ],
        ];
    }
}
