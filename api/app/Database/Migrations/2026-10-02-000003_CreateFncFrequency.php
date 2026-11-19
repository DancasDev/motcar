<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFncFrequency extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'    => 'LONGTEXT',
                'comment' => 'JSON multilingüe ej: {"es":"Quincenal","en":"Biweekly"}',
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
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

        $this->forge->createTable('fnc_frequency', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Frecuencias de cobro y aportes'",
        ]);

        $this->forge->processIndexes('fnc_frequency');
    }

    public function down(): void {
        $this->forge->dropTable('fnc_frequency');
    }
}
