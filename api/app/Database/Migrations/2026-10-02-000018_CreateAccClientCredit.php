<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccClientCredit extends Migration {
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
            'receipt_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'payment_account_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'currency_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'comment'    => 'Monto nominal en moneda de origen',
            ],
            'exchange_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,4',
                'comment'    => 'Tasa de cambio aplicada al momento del cobro',
            ],
            'available_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'comment'    => 'Saldo disponible en moneda de origen',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2'],
                'default'    => '0',
                'comment'    => '0=Disponible, 1=Agotado, 2=Anulado',
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
        $this->forge->addKey('receipt_id');
        $this->forge->addKey('status');

        $this->forge->createTable('acc_client_credit', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Saldos a favor y anticipos de clientes generados por sobrepago'",
        ]);

        $this->forge->addForeignKey('branch_id', 'glb_branch', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_cc_branch_id');
        $this->forge->addForeignKey('client_id', 'glb_person', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_cc_client_id');
        $this->forge->addForeignKey('receipt_id', 'acc_receipt', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_cc_receipt_id');
        $this->forge->addForeignKey('payment_account_id', 'acc_payment_account', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_cc_payment_account_id');
        $this->forge->addForeignKey('currency_id', 'acc_currency', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_cc_currency_id');
        $this->forge->processIndexes('acc_client_credit');
    }

    public function down(): void {
        $this->forge->dropForeignKey('acc_client_credit', 'fk_acc_cc_currency_id');
        $this->forge->dropForeignKey('acc_client_credit', 'fk_acc_cc_payment_account_id');
        $this->forge->dropForeignKey('acc_client_credit', 'fk_acc_cc_receipt_id');
        $this->forge->dropForeignKey('acc_client_credit', 'fk_acc_cc_client_id');
        $this->forge->dropForeignKey('acc_client_credit', 'fk_acc_cc_branch_id');
        $this->forge->dropTable('acc_client_credit');
    }
}
