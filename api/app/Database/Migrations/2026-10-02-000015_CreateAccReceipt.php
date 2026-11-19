<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccReceipt extends Migration {
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
            'collector_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'date' => [
                'type' => 'DATETIME',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'cancellation_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2'],
                'default'    => '0',
                'comment'    => '0=Borrador, 1=Confirmado, 2=Anulado',
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
        $this->forge->addKey('collector_id');

        $this->forge->createTable('acc_receipt', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Comprobantes y recibos de cobranza de caja'",
        ]);

        $this->forge->addForeignKey('branch_id', 'glb_branch', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_rc_branch_id');
        $this->forge->addForeignKey('client_id', 'glb_person', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_rc_client_id');
        $this->forge->addForeignKey('collector_id', 'gac_user', 'id', 'SET NULL', 'RESTRICT', 'fk_acc_rc_collector_id');
        $this->forge->processIndexes('acc_receipt');
    }

    public function down(): void {
        $this->forge->dropForeignKey('acc_receipt', 'fk_acc_rc_collector_id');
        $this->forge->dropForeignKey('acc_receipt', 'fk_acc_rc_client_id');
        $this->forge->dropForeignKey('acc_receipt', 'fk_acc_rc_branch_id');
        $this->forge->dropTable('acc_receipt');
    }
}
