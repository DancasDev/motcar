<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldsToGacRestriction extends Migration {
    public function up() {
        $this->forge->modifyColumn('gac_restriction', [
            'scope_path' => [
                'name'       => 'scope_path',
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => false,
            ],
        ]);

        $this->forge->addColumn('gac_restriction', [
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

        $this->forge->processIndexes('gac_restriction');
    }

    public function down() {
        $this->forge->dropColumn('gac_restriction', [
            'created_at', 'updated_at',
        ]);

        $this->forge->modifyColumn('gac_restriction', [
            'scope_path' => [
                'name'       => 'scope_path',
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
        ]);
    }
}
