<?php

namespace App\Schemas\Finance;

use DancasDev\DQB\Schema;

class FinancingSchema extends Schema {
    protected array $tablesConfig = [
        'financing' => [
            'name' => 'fnc_financing',
        ],
        'branch' => [
            'name'       => 'glb_branch',
            'dependency' => 'branch_id',
            'join'       => ['type' => 'LEFT', 'on' => 'financing.branch_id = branch.id'],
        ],
        'client' => [
            'name'       => 'glb_person',
            'dependency' => 'client_id',
            'join'       => ['type' => 'LEFT', 'on' => 'financing.client_id = client.id'],
        ],
        'contract_type' => [
            'name'       => 'fnc_contract_type',
            'dependency' => 'contract_type_id',
            'join'       => ['type' => 'LEFT', 'on' => 'financing.contract_type_id = contract_type.id'],
        ],
        'frequency' => [
            'name'       => 'fnc_frequency',
            'dependency' => 'frequency_id',
            'join'       => ['type' => 'LEFT', 'on' => 'financing.frequency_id = frequency.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                          => [],
        'branch_id'                   => [],
        'branch_name'                 => ['table' => 'branch', 'name' => 'name'],
        'client_id'                   => [],
        'client_first_name'           => ['table' => 'client', 'name' => 'first_name'],
        'client_last_name'            => ['table' => 'client', 'name' => 'last_name'],
        'client_identification_type'  => ['table' => 'client', 'name' => 'identification_type'],
        'client_identification_value' => ['table' => 'client', 'name' => 'identification_value'],
        'client_phone_primary'        => ['table' => 'client', 'name' => 'phone_primary'],
        'contract_type_id'                 => [],
        'contract_type_code'               => ['table' => 'contract_type', 'name' => 'code'],
        'contract_type_name'               => ['table' => 'contract_type', 'name' => 'name'],
        'contract_type_metadata_schema_id' => ['table' => 'contract_type', 'name' => 'metadata_schema_id'],
        'frequency_id'                     => [],
        'frequency_code'                   => ['table' => 'frequency', 'name' => 'code'],
        'frequency_name'                   => ['table' => 'frequency', 'name' => 'name'],
        'code'                        => [],
        'total_amount'                => [],
        'initial_amount'              => [],
        'installment_count'           => [],
        'installment_amount'          => [],
        'initial_due_date'            => [],
        'frequency_base_date'         => [],
        'status'                      => [],
        'is_overdue'                  => [],
        'awarded_at'                  => [],
        'benefit_metadata'            => [],
        'is_disabled'                 => [],
        'created_at'                  => [],
        'updated_at'                  => [],
        'deleted_at'                  => ['access_level' => 2],
    ];

    public const FIELDS_CALCULATED = [
        'financed_amount' => ['total_amount', 'initial_amount'],
    ];

    public static function fieldsCalculatedCallback(array $records, array $fieldsCalculated, callable $removeFieldsFunction): array {
        $calcFinanced = in_array('financed_amount', $fieldsCalculated, true);

        foreach ($records as $key => $item) {
            if ($calcFinanced) {
                $records[$key]['financed_amount'] = round((float) $item['total_amount'] - (float) $item['initial_amount'], 2);
            }
            $records[$key] = $removeFieldsFunction($records[$key]);
        }

        return $records;
    }
}
