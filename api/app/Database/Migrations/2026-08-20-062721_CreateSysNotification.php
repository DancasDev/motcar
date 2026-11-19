<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysNotification extends Migration
{
    public function up()
    {
        $options = [
            'ENGINE'     => 'InnoDB',
            'CHARSET'    => 'utf8mb4',
            'COLLATE'    => 'utf8mb4_unicode_ci',
            'ROW_FORMAT' => 'DYNAMIC',   // permite off-page storage eficiente para JSON/TEXT
        ];

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // --- Destinatario ---
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'comment'  => 'Destinatario (gac_user.id)',
            ],
            'notification_type_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'comment'  => 'Tipo de notificacion (sys_notification_type.id)',
            ],
            // --- Canal ---
            'channel_type' => [
                'type'     => 'TINYINT',
                'unsigned' => true,
                'comment'  => '0=InApp, 1=Email, 2=Push',
            ],
            'channel_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,       // max indexable con utf8mb4 en innodb 5.7+
                'null'       => true,
                'comment'    => 'Destino del canal (email, device token); NULL = InApp',
            ],
            // --- Contenido ---
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'comment'    => 'Asunto o titulo del mensaje',
            ],
            'message' => [
                'type'    => 'TEXT',
                'comment' => 'Contenido principal del mensaje',
            ],
            'payload' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Metadatos adicionales: type_code, action_url, variables',
            ],
            // --- Control de entrega (outbox pattern) ---
            'status' => [
                'type'     => 'TINYINT',
                'unsigned' => true,
                'default'  => 0,
                'comment'  => '0=pendiente, 1=enviado, 2=procesando, 3=leido, 4=dead_letter',
            ],
            'retry_count' => [
                'type'     => 'TINYINT',
                'unsigned' => true,
                'default'  => 0,
                'comment'  => 'Intentos realizados',
            ],
            'max_retries' => [
                'type'     => 'TINYINT',
                'unsigned' => true,
                'default'  => 3,
                'comment'  => 'Maximo de reintentos antes de dead_letter',
            ],
            'next_attempt_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Cuando se puede reintentar; NULL = disponible ahora',
            ],
            'error_message' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'Ultimo error al intentar el envio',
            ],
            // --- Timestamps ---
            'sent_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Fecha/hora exacta de envio',
            ],
            'read_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Fecha/hora en que el usuario leyo la notificacion (InApp)',
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

        // Dispatcher: consulta critica — pendientes por canal listos para enviar
        // WHERE channel_type = ? AND status = 0 AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
        $this->forge->addKey(['channel_type', 'status', 'next_attempt_at'], false, false, 'idx_dispatch');

        // Bandeja In-App: WHERE user_id = ? AND channel_type = 0 AND deleted_at IS NULL ORDER BY id DESC
        $this->forge->addKey(['user_id', 'channel_type', 'status'], false, false, 'idx_inapp_user');

        // Purga: WHERE status IN (1,4) AND created_at < ? AND deleted_at IS NULL
        $this->forge->addKey(['status', 'created_at'], false, false, 'idx_purge');

        $this->forge->createTable('sys_notification', true, $options);
    }

    public function down()
    {
        $this->forge->dropTable('sys_notification', true);
    }
}