<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldsToGacPermission extends Migration {
    public function up() {
        $this->forge->modifyColumn('gac_permission', [
            'scope_path' => [
                'name'       => 'scope_path',
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => false,
            ],
        ]);

        $this->forge->addColumn('gac_permission', [
            'created_at' => [
                'type'  => 'DATETIME',
                'null'  => false,
                'after' => 'is_disabled',
            ],
            'updated_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'created_at',
            ],
        ]);

        $this->forge->processIndexes('gac_permission');
    }

    public function down() {
        $this->forge->dropColumn('gac_permission', [
            'created_at', 'updated_at',
        ]);

        $this->forge->modifyColumn('gac_permission', [
            'scope_path' => [
                'name'       => 'scope_path',
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
        ]);
    }
}
