<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldsToGacClient extends Migration {
    public function up() {
        $this->forge->addColumn('gac_client', [
            'description' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'name',
            ],
            'client_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'after'      => 'description',
                'comment'    => 'Identificador publico del cliente (similar a un username)',
            ],
            'client_secret' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'after'      => 'client_id',
                'comment'    => 'Secreto del cliente (DEBE almacenarse hasheado)',
            ],
            'failed_attempt_count' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'after' => 'client_secret',
            ],
            'failed_attempt_date' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'failed_attempt_count',
            ],
            'last_login_date' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'failed_attempt_date',
            ],
            'last_login_ip' => [
                'type' => 'VARCHAR',
                'constraint' => 39,
                'null' => true,
                'after' => 'last_login_date',
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

        $this->forge->addUniqueKey('client_id');

        $this->forge->processIndexes('gac_client');
    }

    public function down() {
        $this->forge->dropKey('gac_client', 'client_id');

        $this->forge->dropColumn('gac_client', [
            'description', 'client_id', 'client_secret',
            'failed_attempt_count', 'failed_attempt_date',
            'last_login_date', 'last_login_ip',
            'created_at', 'updated_at',
        ]);
    }
}
