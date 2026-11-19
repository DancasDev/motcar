<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccCurrency extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 3,
                'comment'    => 'Codigo ISO 4217 ej: USD, VES',
            ],
            'symbol' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
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

        $this->forge->createTable('acc_currency', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Monedas reconocidas por el sistema'",
        ]);

        $this->forge->processIndexes('acc_currency');
    }

    public function down(): void {
        $this->forge->dropTable('acc_currency');
    }
}
