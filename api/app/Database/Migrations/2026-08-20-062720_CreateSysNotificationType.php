<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysNotificationType extends Migration
{
    public function up()
    {
        $options = [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ];

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'comment'    => 'Codigo unico del tipo de notificacion',
            ],
            'name' => [
                'type'    => 'JSON',
                'comment' => 'Nombre del tipo de notificacion en formato i18n',
            ],
            'description' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Descripcion del tipo de notificacion en formato i18n',
            ],
            'channels' => [
                'type'       => 'SET',
                'constraint' => ['0', '1', '2'],
                'default'    => '0,1,2',
                'comment'    => 'Canales permitidos: 0=InApp, 1=Email, 2=Push',
                'after'      => 'code',
            ],
            'action_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'Patron de URL para llamada a la accion (CTA), con soporte {var}',
                'after'      => 'channels',
            ],
            'is_disabled' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'comment'    => '0 = Activo, 1 = Inhabilitado',
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
        $this->forge->addUniqueKey('code', 'uk_sys_notification_type_code');
        $this->forge->createTable('sys_notification_type', true, $options);
    }

    public function down()
    {
        $this->forge->dropTable('sys_notification_type', true);
    }
}