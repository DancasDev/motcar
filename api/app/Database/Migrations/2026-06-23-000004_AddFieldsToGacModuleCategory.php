<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldsToGacModuleCategory extends Migration {
    public function up() {
        $this->forge->addColumn('gac_module_category', [
            'name' => [
                'type'    => 'JSON',
                'comment' => 'Nombre de la categoria en formato i18n',
                'after'   => 'code',
            ],
            'description' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Descripcion de la categoria en formato i18n',
                'after'   => 'name',
            ],
            'scope' => [
                'type'       => 'SET',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'comment'    => 'Alcance de la categoria: 0=Sistema, 1=Empresa',
                'after'      => 'description',
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
    }

    public function down() {
        $this->forge->dropColumn('gac_module_category', [
            'name', 'description', 'scope', 'created_at', 'updated_at',
        ]);
    }
}
