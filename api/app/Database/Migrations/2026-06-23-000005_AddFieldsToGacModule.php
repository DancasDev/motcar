<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldsToGacModule extends Migration {
    public function up() {
        $this->forge->addColumn('gac_module', [
            'name' => [
                'type'    => 'JSON',
                'comment' => 'Nombre del modulo en formato i18n',
                'after'   => 'code',
            ],
            'description' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Descripcion del modulo en formato i18n',
                'after'   => 'name',
            ],
            'scope' => [
                'type'       => 'SET',
                'constraint' => ['0', '1'],
                'default'    => '0',
                'comment'    => 'Alcance del modulo: 0=Sistema, 1=Empresa',
                'after'      => 'description',
            ],
            'payload_schema' => [
                'type'      => 'longtext',
                'null'      => true,
                'default'   => null,
                'comment'   => 'Esquema de la carga útil del módulo.',
                'after'     => 'scope',
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
        $this->forge->dropColumn('gac_module', [
            'name', 'description', 'scope', 'payload_schema', 'created_at', 'updated_at',
        ]);
    }
}
