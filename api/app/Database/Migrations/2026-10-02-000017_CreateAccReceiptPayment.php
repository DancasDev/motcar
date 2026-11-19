<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccReceiptPayment extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'receipt_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'origin_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'comment'    => '0 = acc_payment_account, 1 = acc_client_credit',
            ],
            'origin_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'comment'  => 'ID de la cuenta de pago o del saldo a favor',
            ],
            'payment_date' => [
                'type' => 'DATE',
            ],
            'exchange_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,4',
                'default'    => 1.0000,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'comment'    => 'Monto nominal recibido en moneda de la cuenta receptora',
            ],
            'amount_usd' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'comment'    => 'Equivalente en USD calculado por servidor',
            ],
            'reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'file_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
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
        $this->forge->addKey('receipt_id');
        $this->forge->addKey(['origin_type', 'origin_id']);

        $this->forge->createTable('acc_receipt_payment', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Desglose de instrumentos y medios de pago (Split Payment)'",
        ]);

        $this->forge->addForeignKey('receipt_id', 'acc_receipt', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_rp_receipt_id');
        $this->forge->processIndexes('acc_receipt_payment');
    }

    public function down(): void {
        $this->forge->dropForeignKey('acc_receipt_payment', 'fk_acc_rp_receipt_id');
        $this->forge->dropTable('acc_receipt_payment');
    }
}
