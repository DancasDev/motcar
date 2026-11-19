<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysBinnacle extends Migration {
    public function up() {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'from_entity_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2', '3'],
                'comment'    => '0 = Sistema, 1 = Usuario, 2 = Cliente, 3 = Externo',
            ],
            'from_entity_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'to_entity_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'comment'    => 'Tipo de entidad afectada (ej: gac_user, login)',
            ],
            'to_entity_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => 'ID del registro afectado. NULL para eventos generales.',
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'Accion (ej: create, update, failed_login)',
            ],
            'date' => [
                'type' => 'DATETIME',
            ],
            'ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'data' => [
                'type'    => 'LONGTEXT',
                'null'    => true,
                'comment' => 'Detalles del evento en JSON',
            ],
            'session_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'ID del token JWT (jti) que realizo la accion',
            ]
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('action');
        $this->forge->addKey(['to_entity_type', 'to_entity_id']);
        $this->forge->addKey(['from_entity_type', 'from_entity_id']);
        $this->forge->addKey('date');
        $this->forge->addKey('session_id');

        $this->forge->createTable('sys_binnacle', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);

        $this->forge->processIndexes('sys_binnacle');
    }

    public function down() {
        $this->forge->dropTable('sys_binnacle');
    }
}
