<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccReceivable extends Migration {
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
            'receivable_concept_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'origin_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0'],
                'default'    => '0',
                'comment'    => '0 = fnc_san_participant',
            ],
            'origin_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'total_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
            ],
            'paid_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => 0.00,
            ],
            'issue_date' => [
                'type' => 'DATE',
            ],
            'due_date' => [
                'type' => 'DATE',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2', '3'],
                'default'    => '0',
                'comment'    => '0=Pendiente, 1=Parcial, 2=Pagado, 3=Anulado',
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
        $this->forge->addKey('branch_id');
        $this->forge->addKey('client_id');
        $this->forge->addKey('receivable_concept_id');
        $this->forge->addKey(['origin_type', 'origin_id']);
        $this->forge->addKey('due_date');
        $this->forge->addKey('status');

        $this->forge->createTable('acc_receivable', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Titulos de cuentas por cobrar unificadas en USD'",
        ]);

        $this->forge->addForeignKey('branch_id', 'glb_branch', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_rv_branch_id');
        $this->forge->addForeignKey('client_id', 'glb_person', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_rv_client_id');
        $this->forge->addForeignKey('receivable_concept_id', 'acc_receivable_concept', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_rv_concept_id');
        $this->forge->processIndexes('acc_receivable');
    }

    public function down(): void {
        $this->forge->dropForeignKey('acc_receivable', 'fk_acc_rv_concept_id');
        $this->forge->dropForeignKey('acc_receivable', 'fk_acc_rv_client_id');
        $this->forge->dropForeignKey('acc_receivable', 'fk_acc_rv_branch_id');
        $this->forge->dropTable('acc_receivable');
    }
}
