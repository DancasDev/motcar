<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlbPerson extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'identification_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2', '3'],
                'default'    => '0',
                'comment'    => '0=V, 1=E, 2=J, 3=P',
            ],
            'identification_value' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'first_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'last_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'phone_primary' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'phone_secondary' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'city' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            'metadata' => [
                'type'    => 'LONGTEXT',
                'null'    => true,
                'comment' => 'JSON para datos complementarios',
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
        $this->forge->addUniqueKey(['identification_type', 'identification_value']);

        $this->forge->createTable('glb_person', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Directorio unificado de personas (clientes y asesores)'",
        ]);

        $this->forge->processIndexes('glb_person');
    }

    public function down(): void {
        $this->forge->dropTable('glb_person');
    }
}
