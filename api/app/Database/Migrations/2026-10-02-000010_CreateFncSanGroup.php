<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFncSanGroup extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'branch_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'frequency_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'contract_type_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'installment_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
            ],
            'installment_count' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'unsigned'   => true,
            ],
            'participant_max' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 10,
            ],
            'participant_count' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'frequency_base_date' => [
                'type' => 'DATE',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2'],
                'default'    => '0',
                'comment'    => '0=En formacion, 1=Activo, 2=Finalizado',
            ],
            'is_disabled' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->addKey('branch_id');
        $this->forge->addKey('frequency_id');
        $this->forge->addKey('contract_type_id');

        $this->forge->createTable('fnc_san_group', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Circulos y grupos de ahorro colectivo SAN'",
        ]);

        $this->forge->addForeignKey('branch_id', 'glb_branch', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_sg_branch_id');
        $this->forge->addForeignKey('frequency_id', 'fnc_frequency', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_sg_frequency_id');
        $this->forge->addForeignKey('contract_type_id', 'fnc_contract_type', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_sg_contract_type_id');
        $this->forge->processIndexes('fnc_san_group');
    }

    public function down(): void {
        $this->forge->dropForeignKey('fnc_san_group', 'fk_fnc_sg_contract_type_id');
        $this->forge->dropForeignKey('fnc_san_group', 'fk_fnc_sg_frequency_id');
        $this->forge->dropForeignKey('fnc_san_group', 'fk_fnc_sg_branch_id');
        $this->forge->dropTable('fnc_san_group');
    }
}
