<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldsToGacUser extends Migration {
    public function up() {
        $this->forge->addColumn('gac_user', [
            'country_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
                'after' => 'id',
            ],
            'language_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
                'after' => 'country_id',
            ],
            'time_zone_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
                'after' => 'language_id',
            ],
            'photo_url' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
                'after' => 'password',
            ],
            'signature_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'URL de la firma manuscrita digitalizada',
                'after'      => 'photo_url',
            ],
            'first_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => false,
                'after'      => 'signature_url',
            ],
            'last_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => false,
                'after'      => 'first_name',
            ],
            'sex' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'null'       => true,
                'comment'    => '0 = Masculino, 1 = Femenino',
                'after'      => 'last_name',
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => false,
                'after'      => 'sex',
            ],
            'email_verified_date' => [
                'type'       => 'DATETIME',
                'null'       => true,
                'after'      => 'email',
            ],
            'failed_attempt_date' => [
                'type'       => 'DATETIME',
                'null'       => true,
                'after'      => 'email_verified_date',
            ],
            'failed_attempt_count' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'failed_attempt_date',
            ],
            'last_login_date' => [
                'type'       => 'DATETIME',
                'null'       => true,
                'after'      => 'failed_attempt_count',
            ],
            'last_login_ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 39,
                'null'       => true,
                'after'      => 'last_login_date',
            ],
            'force_to' => [
                'type'       => 'SET',
                'constraint' => ['0', '1'],
                'null'       => true,
                'comment'    => '0 = Cambiar contraseña, 1 = Verificar Email',
                'after'      => 'last_login_ip',
            ],
            'notification_channels' => [
                'type'       => 'SET',
                'constraint' => ['1', '2'],
                'null'       => true,
                'comment'    => 'Canales configurables: 1 = Email, 2 = Push (In-App es permanente)',
                'after'      => 'force_to',
            ],
            'telegram_chat_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Chat privado vinculado de Telegram',
                'after'      => 'notification_channels',
            ],
            'telegram_username' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Username de Telegram vinculado',
                'after'      => 'telegram_chat_id',
            ],
            'metadata' => [
                'type'    => 'LONGTEXT',
                'null'    => true,
                'comment' => 'JSON con los valores de metadatos/campos dinámicos',
                'after'   => 'telegram_username',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
                'after' => 'is_disabled',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'created_at',
            ],
        ]);
        
        $this->forge->addUniqueKey('email');

        $this->forge->addForeignKey('country_id', 'glb_country', 'id', 'CASCADE', 'RESTRICT', 'fk_gac_user_country_id');
        $this->forge->addForeignKey('language_id', 'glb_language', 'id', 'CASCADE', 'RESTRICT', 'fk_gac_user_language_id');
        $this->forge->addForeignKey('time_zone_id', 'glb_time_zone', 'id', 'CASCADE', 'RESTRICT', 'fk_gac_user_time_zone_id');
        $this->forge->processIndexes('gac_user');
    }

    public function down() {
        $this->forge->dropForeignKey('gac_user', 'fk_gac_user_time_zone_id');
        $this->forge->dropForeignKey('gac_user', 'fk_gac_user_language_id');
        $this->forge->dropForeignKey('gac_user', 'fk_gac_user_country_id');

        $this->forge->dropKey('gac_user', 'email');

        $this->forge->dropColumn('gac_user', [
            'photo_url', 'signature_url', 'first_name', 'last_name', 'sex',
            'country_id', 'language_id', 'time_zone_id', 'email',
            'email_verified_date', 'failed_attempt_date', 'failed_attempt_count',
            'last_login_date', 'last_login_ip', 'force_to',
            'notification_channels', 'telegram_chat_id', 'telegram_username',
            'metadata',
            'created_at', 'updated_at',
        ]);
    }
}
