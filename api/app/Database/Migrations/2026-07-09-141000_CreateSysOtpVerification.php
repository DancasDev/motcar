<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSysOtpVerification extends Migration {
    public function up() {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'otp_action_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'user_id' => [
                'type' => 'INT',
            ],
            'otp_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2', '3'],
                'default'    => '0',
                'comment'    => '0=Pendiente, 1=Verificado, 2=Agotado(intentos), 3=Expirado',
            ],
            'failed_attempt_count' => [
                'type'    => 'tinyint',
                'unsigned'=> true,
                'default' => 0,
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
        $this->forge->addKey(['user_id', 'otp_action_id', 'status', 'deleted_at'], false, false, 'pending_lookup');
        $this->forge->addKey('otp_action_id');

        $this->forge->createTable('sys_otp_verification', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);

        $this->forge->addForeignKey('otp_action_id', 'sys_otp_action', 'id', 'CASCADE', 'RESTRICT', 'fk_sys_otp_verification_otp_action_id');
        $this->forge->addForeignKey('user_id', 'gac_user', 'id', 'CASCADE', 'CASCADE', 'fk_sys_otp_verification_user_id');
        $this->forge->processIndexes('sys_otp_verification');
    }

    public function down() {
        $this->forge->dropForeignKey('sys_otp_verification', 'fk_sys_otp_verification_user_id');
        $this->forge->dropForeignKey('sys_otp_verification', 'fk_sys_otp_verification_otp_action_id');
        $this->forge->dropTable('sys_otp_verification');
    }
}
