<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ModifyDeletedAtFielDInGacTables extends Migration {
    private array $tables = [
        'gac_user', 'gac_client', 'gac_role', 'gac_role_entity',
        'gac_module_category', 'gac_module',
        'gac_permission', 'gac_restriction',
    ];

    public function up() {
        foreach ($this->tables as $table) {
            $this->forge->addColumn($table, [
                'deleted_at_ts' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->db->table($table)
                ->set('deleted_at_ts', 'FROM_UNIXTIME(deleted_at)', false)
                ->where('deleted_at IS NOT NULL')
                ->update();

            $this->forge->dropColumn($table, 'deleted_at');

            $this->forge->modifyColumn($table, [
                'deleted_at_ts' => [
                    'name' => 'deleted_at',
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down() {
        foreach ($this->tables as $table) {
            $this->forge->addColumn($table, [
                'deleted_at_bi' => [
                    'type' => 'BIGINT',
                    'null' => true,
                ],
            ]);

            $this->db->table($table)
                ->set('deleted_at_bi', 'UNIX_TIMESTAMP(deleted_at)', false)
                ->where('deleted_at IS NOT NULL')
                ->update();

            $this->forge->dropColumn($table, 'deleted_at');

            $this->forge->modifyColumn($table, [
                'deleted_at_bi' => [
                    'name' => 'deleted_at',
                    'type' => 'BIGINT',
                    'null' => true,
                ],
            ]);
        }
    }
}
