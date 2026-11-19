<?php

namespace App\Database\Seeds\Accounting;

use CodeIgniter\Database\Seeder;

class PaymentAccountCategorySeeder extends Seeder {
    public function run(): void {
        $db = $this->db;
        $builder = $db->table('acc_payment_account_category');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $schemas = array_column(
            $db->table('glb_metadata_schema')->select('id, code')->get()->getResultArray(),
            'id',
            'code'
        );

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            $schemaCode = $row['schema_code'] ?? null;
            unset($row['schema_code']);

            $schemaId = $schemas[$schemaCode] ?? null;
            $row['metadata_schema_id'] = $schemaId;

            // Buscar por schema_id si existe, o por nombre
            $query = $builder->select('id');
            if ($schemaId) {
                $query->where('metadata_schema_id', $schemaId);
            } else {
                $query->where('name', $row['name']);
            }
            $existing = $query->limit(1)->get()->getRow();

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

        // Eliminar categoría 'Apertura SAM' si existe
        $aperturaCategory = $builder->select('id')->where('name', 'Apertura SAM')->limit(1)->get()->getRow();
        if ($aperturaCategory) {
            // Reasignar cuentas vinculadas a Efectivo si existen
            $cashCategory = $builder->select('id')->where('name', 'Efectivo')->limit(1)->get()->getRow();
            $cashCatId = $cashCategory ? $cashCategory->id : 1;
            $db->table('acc_payment_account')->where('category_id', $aperturaCategory->id)->update(['category_id' => $cashCatId]);

            $builder->where('id', $aperturaCategory->id)->delete();
            echo "  [-] Categoria 'Apertura SAM' eliminada" . PHP_EOL;
        }

        echo "  Categorias de cuentas de pago insertadas: {$inserted}, actualizadas: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [];
    }

    private function getCustomData(): array {
        return [
            [
                'name'        => 'Efectivo',
                'schema_code' => 'payment_account_cash',
                'is_disabled' => '0',
            ],
            [
                'name'        => 'Pago Móvil',
                'schema_code' => 'payment_account_mobile_payment',
                'is_disabled' => '0',
            ],
            [
                'name'        => 'Transferencia',
                'schema_code' => 'payment_account_bank_transfer',
                'is_disabled' => '0',
            ],
            [
                'name'        => 'Zelle',
                'schema_code' => 'payment_account_zelle',
                'is_disabled' => '0',
            ],
        ];
    }
}
