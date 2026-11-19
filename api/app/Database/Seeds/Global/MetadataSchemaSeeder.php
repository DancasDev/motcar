<?php

namespace App\Database\Seeds\Global;

use CodeIgniter\Database\Seeder;

class MetadataSchemaSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('glb_metadata_schema');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            if (isset($row['unique_keys']) && is_array($row['unique_keys'])) {
                $row['unique_keys'] = json_encode($row['unique_keys'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $existing = $builder->select('id')->where('code', $row['code'])->limit(1)->get()->getRow();
            if ($existing) {
                if (array_key_exists('unique_keys', $row) && $row['unique_keys'] === null) {
                    unset($row['unique_keys']);
                }
                $row['updated_at'] = $time;
                $builder->where('id', $existing->id)->update($row);
                $updated++;
            } else {
                $row['created_at'] = $time;
                $builder->insert($row);
                $inserted++;
            }
        }

        echo "  Esquemas de metadatos insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [
            [
                'code'           => 'gac_user',
                'table_name'     => 'gac_user',
                'storage_column' => 'metadata',
                'unique_keys'    => null,
                'is_disabled'    => '0',
            ],
        ];
    }

    private function getCustomData(): array {
        return [
            [
                'code'           => 'payment_account_cash',
                'table_name'     => 'acc_payment_account',
                'storage_column' => 'metadata',
                'unique_keys'    => null,
                'is_disabled'    => '0',
            ],
            [
                'code'           => 'payment_account_mobile_payment',
                'table_name'     => 'acc_payment_account',
                'storage_column' => 'metadata',
                'unique_keys'    => null,
                'is_disabled'    => '0',
            ],
            [
                'code'           => 'payment_account_bank_transfer',
                'table_name'     => 'acc_payment_account',
                'storage_column' => 'metadata',
                'unique_keys'    => null,
                'is_disabled'    => '0',
            ],
            [
                'code'           => 'payment_account_zelle',
                'table_name'     => 'acc_payment_account',
                'storage_column' => 'metadata',
                'unique_keys'    => null,
                'is_disabled'    => '0',
            ],
            [
                'code'           => 'contract_type_1',
                'table_name'     => 'fnc_san_participant',
                'storage_column' => 'benefit_metadata',
                'unique_keys'    => null,
                'is_disabled'    => '0',
            ],
        ];
    }
}
