<?php

namespace App\Database\Seeds\Global;

use CodeIgniter\Database\Seeder;

class MetadataSchemaFieldSeeder extends Seeder {
    public function run(): void {
        $db = $this->db;
        $builder = $db->table('glb_metadata_schema_field');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $schemas = array_column(
            $db->table('glb_metadata_schema')->select('id, code')->get()->getResultArray(),
            'id',
            'code'
        );

        $types = array_column(
            $db->table('glb_metadata_field_type')->select('id, code')->get()->getResultArray(),
            'id',
            'code'
        );

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            $schemaCode = $row['schema_code'] ?? null;
            $typeCode   = $row['type_code'] ?? null;
            unset($row['schema_code'], $row['type_code']);

            $schemaId = $schemas[$schemaCode] ?? null;
            $typeId   = $types[$typeCode] ?? null;

            if (!$schemaId || !$typeId) {
                continue;
            }

            $row['metadata_schema_id']     = $schemaId;
            $row['metadata_field_type_id'] = $typeId;

            if (isset($row['attribute_values']) && is_array($row['attribute_values'])) {
                $row['attribute_values'] = json_encode($row['attribute_values'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            if (isset($row['rules']) && is_array($row['rules'])) {
                $row['rules'] = json_encode($row['rules'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $existing = $builder->select('id')
                ->where('metadata_schema_id', $schemaId)
                ->where('code', $row['code'])
                ->limit(1)
                ->get()
                ->getRow();

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

        // Eliminar campos obsoletos si existen
        $pmSchemaId = $schemas['payment_account_mobile_payment'] ?? null;
        if ($pmSchemaId) {
            $builder->where('metadata_schema_id', $pmSchemaId)->where('code', 'bank_code')->delete();
        }

        $btSchemaId = $schemas['payment_account_bank_transfer'] ?? null;
        if ($btSchemaId) {
            $builder->where('metadata_schema_id', $btSchemaId)->where('code', 'bank_name')->delete();
        }

        echo "  Campos de esquemas de metadatos insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [];
    }

    private function getCustomData(): array {
        return array_merge(
            [
                // payment_account_mobile_payment
                [
                    'schema_code'      => 'payment_account_mobile_payment',
                    'type_code'        => 'text',
                    'code'             => 'id_document',
                    'attribute_values' => ['label' => 'Documento de Identidad'],
                    'position'         => 1,
                    'is_disabled'      => '0',
                ],
                [
                    'schema_code'      => 'payment_account_mobile_payment',
                    'type_code'        => 'tel',
                    'code'             => 'phone_number',
                    'attribute_values' => ['label' => 'Teléfono'],
                    'position'         => 2,
                    'is_disabled'      => '0',
                ],
                [
                    'schema_code'      => 'payment_account_mobile_payment',
                    'type_code'        => 'text',
                    'code'             => 'account_holder',
                    'attribute_values' => ['label' => 'Propietario (Dueño de la Cuenta)'],
                    'position'         => 3,
                    'is_disabled'      => '0',
                ],

                // payment_account_bank_transfer
                [
                    'schema_code'      => 'payment_account_bank_transfer',
                    'type_code'        => 'text',
                    'code'             => 'account_number',
                    'attribute_values' => ['label' => 'Número de Cuenta'],
                    'position'         => 1,
                    'is_disabled'      => '0',
                ],
                [
                    'schema_code'      => 'payment_account_bank_transfer',
                    'type_code'        => 'text',
                    'code'             => 'id_document',
                    'attribute_values' => ['label' => 'Documento de Identidad'],
                    'position'         => 2,
                    'is_disabled'      => '0',
                ],
                [
                    'schema_code'      => 'payment_account_bank_transfer',
                    'type_code'        => 'text',
                    'code'             => 'beneficiary_name',
                    'attribute_values' => ['label' => 'Nombre del Beneficiario'],
                    'position'         => 3,
                    'is_disabled'      => '0',
                ],

                // payment_account_zelle
                [
                    'schema_code' => 'payment_account_zelle',
                    'type_code'   => 'email',
                    'code'        => 'email',
                    'position'    => 1,
                    'is_disabled' => '0',
                ],
                [
                    'schema_code' => 'payment_account_zelle',
                    'type_code'   => 'text',
                    'code'        => 'account_holder',
                    'position'    => 2,
                    'is_disabled' => '0',
                ],
            ],
            $this->getMotorcycleFields('contract_type_1'),
            $this->getMotorcycleFields('contract_type_2')
        );
    }

    /**
     * Campos del beneficio motocicleta para esquemas de contratos SAN y Financiamiento.
     */
    private function getMotorcycleFields(string $schemaCode): array {
        return [
            [
                'schema_code' => $schemaCode,
                'type_code'   => 'text',
                'code'        => 'brand',
                'position'    => 1,
                'rules'       => [
                    ['code' => 'required'],
                    ['code' => 'string'],
                    ['code' => 'max_length', 'params' => ['value' => 50]],
                ],
                'is_disabled' => '0',
            ],
            [
                'schema_code' => $schemaCode,
                'type_code'   => 'text',
                'code'        => 'model',
                'position'    => 2,
                'rules'       => [
                    ['code' => 'required'],
                    ['code' => 'string'],
                    ['code' => 'max_length', 'params' => ['value' => 50]],
                ],
                'is_disabled' => '0',
            ],
            [
                'schema_code' => $schemaCode,
                'type_code'   => 'number',
                'code'        => 'year',
                'position'    => 3,
                'rules'       => [
                    ['code' => 'required'],
                    ['code' => 'numeric'],
                ],
                'is_disabled' => '0',
            ],
            [
                'schema_code' => $schemaCode,
                'type_code'   => 'text',
                'code'        => 'plate',
                'position'    => 4,
                'rules'       => [
                    ['code' => 'string'],
                    ['code' => 'max_length', 'params' => ['value' => 20]],
                ],
                'is_disabled' => '0',
            ],
            [
                'schema_code' => $schemaCode,
                'type_code'   => 'number',
                'code'        => 'reference_value',
                'position'    => 5,
                'rules'       => [
                    ['code' => 'required'],
                    ['code' => 'numeric'],
                    ['code' => 'greater_than', 'params' => ['value' => 0]],
                ],
                'is_disabled' => '0',
            ],
            [
                'schema_code' => $schemaCode,
                'type_code'   => 'text',
                'code'        => 'chassis_serial',
                'position'    => 6,
                'rules'       => [
                    ['code' => 'string'],
                    ['code' => 'max_length', 'params' => ['value' => 40]],
                ],
                'is_disabled' => '0',
            ],
            [
                'schema_code' => $schemaCode,
                'type_code'   => 'text',
                'code'        => 'engine_serial',
                'position'    => 7,
                'rules'       => [
                    ['code' => 'string'],
                    ['code' => 'max_length', 'params' => ['value' => 40]],
                ],
                'is_disabled' => '0',
            ],
            [
                'schema_code' => $schemaCode,
                'type_code'   => 'text',
                'code'        => 'color',
                'position'    => 8,
                'rules'       => [
                    ['code' => 'string'],
                    ['code' => 'max_length', 'params' => ['value' => 30]],
                ],
                'is_disabled' => '0',
            ],
            [
                'schema_code'      => $schemaCode,
                'type_code'        => 'file',
                'code'             => 'photos',
                'position'         => 9,
                'attribute_values' => [
                    'label'      => ['es' => 'Fotografías del Vehículo', 'en' => 'Vehicle Photographs'],
                    'upload_url' => '/v1/system/storage/images/contracts/deliveries',
                    'locker_key' => 'images/contracts/deliveries',
                    'accept'     => 'image/jpeg,image/png,image/webp',
                    'max_size'   => 5120,
                    'multiple'   => true,
                ],
                'rules'            => [
                    ['code' => 'valid_url'],
                ],
                'is_disabled'      => '0',
            ],
        ];
    }
}
