<?php

namespace App\Schemas\Finance;

use DancasDev\DQB\Schema;

class SanParticipantSchema extends Schema {
    protected array $tablesConfig = [
        'san_participant' => [
            'name' => 'fnc_san_participant',
        ],
        'branch' => [
            'name'       => 'glb_branch',
            'dependency' => 'branch_id',
            'join'       => ['type' => 'LEFT', 'on' => 'san_participant.branch_id = branch.id'],
        ],
        'san_group' => [
            'name'       => 'fnc_san_group',
            'dependency' => 'san_group_id',
            'join'       => ['type' => 'LEFT', 'on' => 'san_participant.san_group_id = san_group.id'],
        ],
        'client' => [
            'name'       => 'glb_person',
            'dependency' => 'client_id',
            'join'       => ['type' => 'LEFT', 'on' => 'san_participant.client_id = client.id'],
        ],
        'referrer' => [
            'name'       => 'glb_person',
            'dependency' => 'referrer_id',
            'join'       => ['type' => 'LEFT', 'on' => 'san_participant.referrer_id = referrer.id'],
        ],
        'frequency' => [
            'name'       => 'fnc_frequency',
            'dependency' => 'frequency_id',
            'join'       => ['type' => 'LEFT', 'on' => 'san_participant.frequency_id = frequency.id'],
        ],
    ];

    protected array $fieldsConfig = [
        'id'                  => [],
        'branch_id'           => [],
        'branch_name'         => ['table' => 'branch', 'name' => 'name'],
        'san_group_id'        => [],
        'san_group_name'      => ['table' => 'san_group', 'name' => 'name'],
        'client_id'           => [],
        'client_first_name'   => ['table' => 'client', 'name' => 'first_name'],
        'client_last_name'    => ['table' => 'client', 'name' => 'last_name'],
        'referrer_id'         => [],
        'referrer_first_name' => ['table' => 'referrer', 'name' => 'first_name'],
        'referrer_last_name'  => ['table' => 'referrer', 'name' => 'last_name'],
        'frequency_id'        => [],
        'frequency_code'      => ['table' => 'frequency', 'name' => 'code'],
        'position'            => [],
        'frequency_base_date' => [],
        'installment_amount'  => [],
        'installment_count'   => [],
        'status'              => [],
        'is_overdue'          => [],
        'awarded_at'          => [],
        'benefit_metadata'    => [],
        'is_disabled'         => [],
        'created_at'          => [],
        'updated_at'          => [],
        'deleted_at'          => ['access_level' => 2],
    ];
}
