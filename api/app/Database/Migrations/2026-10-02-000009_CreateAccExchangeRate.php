<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccExchangeRate extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'currency_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'date' => [
                'type' => 'DATE',
            ],
            'value' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,4',
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
        $this->forge->addUniqueKey(['currency_id', 'date']);
        $this->forge->addKey('currency_id');

        $this->forge->createTable('acc_exchange_rate', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Historico de tasas de cambio relativas a USD'",
        ]);

        $this->forge->addForeignKey('currency_id', 'acc_currency', 'id', 'RESTRICT', 'RESTRICT', 'fk_acc_er_currency_id');
        $this->forge->processIndexes('acc_exchange_rate');
    }

    public function down(): void {
        $this->forge->dropForeignKey('acc_exchange_rate', 'fk_acc_er_currency_id');
        $this->forge->dropTable('acc_exchange_rate');
    }
}
