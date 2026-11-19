<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysNotificationDevice extends Migration
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
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'comment'  => 'Usuario propietario del dispositivo (gac_user.id)',
            ],
            'device_uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'comment'    => 'UUID unico del dispositivo/instalacion',
            ],
            'device_type' => [
                'type'       => 'ENUM',
                'constraint' => ['web', 'mobile_android', 'mobile_ios', 'desktop_windows', 'desktop_macos', 'desktop_linux'],
                'comment'    => 'Tipo de plataforma',
            ],
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'comment'    => 'Nombre descriptivo del dispositivo (ej: iPhone 15 Pro, Samsung S24, MacBook Pro)',
            ],
            'os_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'comment'    => 'Nombre del SO (ej: iOS, Android, Windows, macOS, Linux)',
            ],
            'os_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
                'comment'    => 'Version del SO (ej: 18.1, 15.0, 11 23H2)',
            ],
            'app_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
                'comment'    => 'Version de la aplicacion cliente instalada',
            ],
            'push_provider' => [
                'type'       => 'ENUM',
                'constraint' => ['fcm'],
                'default'    => 'fcm',
                'comment'    => 'Proveedor de notificaciones push',
            ],
            'push_token' => [
                'type'    => 'TEXT',
                'comment' => 'Token de registro FCM del dispositivo',
            ],
            'notifications_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'comment'    => '1 = Notificaciones activas en este dispositivo, 0 = Silenciado',
            ],
            'last_active_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Fecha y hora del ultimo acceso o actualizacion del token',
            ],
            'last_ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
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
        $this->forge->addUniqueKey(['user_id', 'device_uuid'], 'uk_user_device_uuid');
        $this->forge->addKey(['user_id', 'notifications_enabled', 'deleted_at'], false, false, 'idx_user_active_device');
        $this->forge->createTable('sys_notification_device', true, $options);
    }

    public function down()
    {
        $this->forge->dropTable('sys_notification_device', true);
    }
}
