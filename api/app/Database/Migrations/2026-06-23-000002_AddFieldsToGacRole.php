<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldsToGacRole extends Migration {
    public function up() {
        $this->forge->addColumn('gac_role', [
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'after'      => 'id',
                'comment'    => 'Código único del rol',
            ],
            'name' => [
                'type'    => 'JSON',
                'after'   => 'code',
                'comment' => 'Nombre del rol en formato i18n',
            ],
            'description' => [
                'type'    => 'JSON',
                'null'    => true,
                'after'   => 'name',
                'comment' => 'Descripción del rol en formato i18n',
            ],
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

        $this->forge->addUniqueKey('code');
        $this->forge->processIndexes('gac_role');
    }

    public function down() {
        foreach (['code', 'name'] as $key) {
            try {
                $this->forge->dropKey('gac_role', $key);
            } catch (\Throwable) {
                // Key may not exist in transition
            }
        }

        $fields = ['code', 'name', 'description', 'created_at', 'updated_at'];
        foreach ($fields as $field) {
            try {
                $this->forge->dropColumn('gac_role', $field);
            } catch (\Throwable) {
                // Column may not exist in transition
            }
        }
    }
}
