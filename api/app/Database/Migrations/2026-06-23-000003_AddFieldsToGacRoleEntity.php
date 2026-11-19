<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldsToGacRoleEntity extends Migration {
    public function up() {
        $this->forge->modifyColumn('gac_role_entity', [
            'scope_path' => [
                'name' => 'scope_path',
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);

        $this->forge->addColumn('gac_role_entity', [
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
    }

    public function down() {
        $this->forge->dropColumn('gac_role_entity', [
            'created_at', 'updated_at',
        ]);

        $this->forge->modifyColumn('gac_role_entity', [
            'scope_path' => [
                'name'       => 'scope_path',
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);
    }
}
