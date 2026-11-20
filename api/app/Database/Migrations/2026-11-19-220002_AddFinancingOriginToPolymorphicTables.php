<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFinancingOriginToPolymorphicTables extends Migration {
    public function up(): void {
        $this->forge->modifyColumn('acc_receivable', [
            'origin_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'null'       => false,
                'comment'    => '0 = fnc_san_participant, 1 = fnc_financing',
            ],
        ]);

        $this->forge->modifyColumn('fnc_contract_type', [
            'scope' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'null'       => false,
                'comment'    => '0 = Participante SAN, 1 = Financiamiento Individual',
            ],
        ]);

        $this->forge->modifyColumn('fnc_contract_document', [
            'origin_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'null'       => false,
                'comment'    => '0 = fnc_san_participant, 1 = fnc_financing',
            ],
        ]);

        $this->forge->modifyColumn('glb_note', [
            'origin_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'null'       => false,
                'comment'    => '0 = fnc_san_participant, 1 = fnc_financing',
            ],
        ]);
    }

    public function down(): void {
        $this->forge->modifyColumn('glb_note', [
            'origin_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0'],
                'default'    => '0',
                'null'       => false,
                'comment'    => '0 = fnc_san_participant',
            ],
        ]);

        $this->forge->modifyColumn('fnc_contract_document', [
            'origin_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0'],
                'default'    => '0',
                'null'       => false,
                'comment'    => '0 = fnc_san_participant',
            ],
        ]);

        $this->forge->modifyColumn('fnc_contract_type', [
            'scope' => [
                'type'       => 'ENUM',
                'constraint' => ['0'],
                'default'    => '0',
                'null'       => false,
                'comment'    => '0 = Participante SAN',
            ],
        ]);

        $this->forge->modifyColumn('acc_receivable', [
            'origin_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0'],
                'default'    => '0',
                'null'       => false,
                'comment'    => '0 = fnc_san_participant',
            ],
        ]);
    }
}
