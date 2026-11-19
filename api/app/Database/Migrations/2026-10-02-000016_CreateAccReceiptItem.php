<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccReceiptItem extends Migration {
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
            'receivable_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'null'       => true,
                'comment'    => 'Monto imputado en USD (NULL en borrador, calculado al confirmar)',
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2', '3'],
                'default'    => '0',
                'comment'    => 'Tipo de imputacion: 0=No definido aun, 1=Pago completo, 2=Abono, 3=Restante',
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
        $this->forge->addKey('receivable_id');

        $this->forge->createTable('acc_receipt_item', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Renglones de imputacion y amortizacion de recibos'",
        ]);

        $this->forge->addForeignKey('receipt_id', 'acc_receipt', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_ri_receipt_id');
        $this->forge->addForeignKey('receivable_id', 'acc_receivable', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_ri_receivable_id');
        $this->forge->processIndexes('acc_receipt_item');
    }

    public function down(): void {
        $this->forge->dropForeignKey('acc_receipt_item', 'fk_acc_ri_receivable_id');
        $this->forge->dropForeignKey('acc_receipt_item', 'fk_acc_ri_receipt_id');
        $this->forge->dropTable('acc_receipt_item');
    }
}
