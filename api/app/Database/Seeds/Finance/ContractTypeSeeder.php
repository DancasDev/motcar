<?php

namespace App\Database\Seeds\Finance;

use CodeIgniter\Database\Seeder;

class ContractTypeSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('fnc_contract_type');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $schemas = array_column(
            $this->db->table('glb_metadata_schema')->select('id, code')->get()->getResultArray(),
            'id',
            'code'
        );

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        $legacy = $builder->select('id')->where('code', 'san_motos_v1')->limit(1)->get()->getRow();
        if ($legacy) {
            $builder->where('id', $legacy->id)->update(['code' => 'san_motorcycles_v1', 'updated_at' => $time]);
        }

        foreach ($list as $row) {
            $schemaCode = $row['schema_code'] ?? null;
            unset($row['schema_code']);

            $row['metadata_schema_id'] = $schemas[$schemaCode] ?? null;

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

        echo "  Tipos de contrato insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [];
    }

    private function getCustomData(): array {
        return [
            [
                'code'        => 'san_motorcycles_v1',
                'name'        => 'SAM de Motos',
                'scope'       => '0',
                'schema_code' => 'contract_type_1',
                'is_disabled' => '0',
            ],
        ];
    }
}
