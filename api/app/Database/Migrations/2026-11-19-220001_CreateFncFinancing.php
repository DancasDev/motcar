<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFncFinancing extends Migration {
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
            'client_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'contract_type_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'frequency_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'total_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
            ],
            'initial_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
            ],
            'installment_count' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
            ],
            'installment_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
            ],
            'frequency_base_date' => [
                'type' => 'DATE',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2', '3'],
                'default'    => '0',
                'comment'    => '0=En espera, 1=Activo, 2=Anulado/Retirado, 3=Culminado',
            ],
            'is_overdue' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
            ],
            'awarded_at' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'benefit_metadata' => [
                'type'    => 'LONGTEXT',
                'null'    => true,
                'comment' => 'JSON para especificaciones del beneficio adjudicado',
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
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('branch_id');
        $this->forge->addKey('client_id');
        $this->forge->addKey('contract_type_id');
        $this->forge->addKey('frequency_id');
        $this->forge->addKey('status');
        $this->forge->addKey('is_overdue');
        $this->forge->addKey('awarded_at');

        $this->forge->createTable('fnc_financing', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Financiamientos individuales y planes de pago directos'",
        ]);

        $this->forge->addForeignKey('branch_id', 'glb_branch', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_fn_branch_id');
        $this->forge->addForeignKey('client_id', 'glb_person', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_fn_client_id');
        $this->forge->addForeignKey('contract_type_id', 'fnc_contract_type', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_fn_contract_type_id');
        $this->forge->addForeignKey('frequency_id', 'fnc_frequency', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_fn_frequency_id');
        $this->forge->processIndexes('fnc_financing');
    }

    public function down(): void {
        $this->forge->dropForeignKey('fnc_financing', 'fk_fnc_fn_frequency_id');
        $this->forge->dropForeignKey('fnc_financing', 'fk_fnc_fn_contract_type_id');
        $this->forge->dropForeignKey('fnc_financing', 'fk_fnc_fn_client_id');
        $this->forge->dropForeignKey('fnc_financing', 'fk_fnc_fn_branch_id');
        $this->forge->dropTable('fnc_financing', true);
    }
}
