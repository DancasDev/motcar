<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStorageLockersTable extends Migration {
    public function up() {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'locker_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'name' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Nombre del locker en formato i18n',
            ],
            'description' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Descripción del locker en formato i18n',
            ],
            'allowed_mimes' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'MIME types permitidos separados por coma (ej: image/jpeg,image/png). Soporta comodin *',
            ],
            'max_size' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Tamaño maximo en KB. NULL = sin limite',
            ],
            'optimize_file' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'comment'    => '0 = No optimizar, 1 = Optimizar',
            ],
            'is_private' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'comment'    => '0 = Publico, 1 = Privado',
            ],
            'is_disabled' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'comment'    => '0 = Habilitado, 1 = Deshabilitado',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
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
        $this->forge->addKey('locker_key', false, true);
        $this->forge->createTable('sys_storage_locker', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down() {
        $this->forge->dropTable('sys_storage_locker');
    }
}
