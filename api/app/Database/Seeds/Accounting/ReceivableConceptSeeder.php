<?php

namespace App\Database\Seeds\Accounting;

use CodeIgniter\Database\Seeder;

class ReceivableConceptSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('acc_receivable_concept');
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

        echo "  Conceptos de cuentas por cobrar insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [];
    }

    private function getCustomData(): array {
        return [
            [
                'code'        => 'base_installment',
                'name'        => 'Cuota Ordinaria Base',
                'template'    => 'Cuota {installment_number} - {group_name}',
                'is_disabled' => '0',
            ],
            [
                'code'        => 'admin_fee',
                'name'        => 'Gastos Administrativos / Cuota de Inscripción',
                'template'    => 'Inscripción y Gestión Administrativa - {group_name}',
                'is_disabled' => '0',
            ],
            [
                'code'        => 'late_fee',
                'name'        => 'Recargo por Mora',
                'template'    => 'Recargo por Mora - Cuota {installment_number}',
                'is_disabled' => '0',
            ],
            [
                'code'        => 'helmet_award',
                'name'        => 'Adjudicación de Casco',
                'template'    => 'Adjudicación de Casco - {group_name}',
                'is_disabled' => '0',
            ],
            [
                'code'        => 'vehicle_difference',
                'name'        => 'Diferencial de Valor del Vehículo',
                'template'    => 'Diferencial de Valor del Vehículo - {group_name}',
                'is_disabled' => '0',
            ],
        ];
    }
}
