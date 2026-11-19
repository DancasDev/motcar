<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccPaymentAccount extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'category_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'currency_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
            ],
            'metadata' => [
                'type'    => 'LONGTEXT',
                'null'    => true,
                'comment' => 'Atributos bancarios validados segun esquema',
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
        $this->forge->addKey('category_id');
        $this->forge->addKey('currency_id');

        $this->forge->createTable('acc_payment_account', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Cajas e instrumentos de cobro y recepcion de fondos'",
        ]);

        $this->forge->addForeignKey('category_id', 'acc_payment_account_category', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_pa_category_id');
        $this->forge->addForeignKey('currency_id', 'acc_currency', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_pa_currency_id');
        $this->forge->processIndexes('acc_payment_account');
    }

    public function down(): void {
        $this->forge->dropForeignKey('acc_payment_account', 'fk_acc_pa_currency_id');
        $this->forge->dropForeignKey('acc_payment_account', 'fk_acc_pa_category_id');
        $this->forge->dropTable('acc_payment_account');
    }
}
