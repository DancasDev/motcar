<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlbMetadataFieldRule extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'comment'    => 'Código de la regla (ej: required, min_length, max_length)',
            ],
            'name' => [
                'type'    => 'JSON',
                'comment' => 'Nombre de la regla en formato i18n',
            ],
            'description' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Descripción de la regla en formato i18n',
            ],
            'parameters_schema' => [
                'type' => 'LONGTEXT',
                'null' => true,
                'comment' => 'Esquema de parámetros de validación',
            ],
            'rule_template' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Plantilla de la regla CI4',
            ],
            'is_disabled' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
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
        $this->forge->addUniqueKey('code');

        $this->forge->createTable('glb_metadata_field_rule', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci'
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('glb_metadata_field_rule');
    }
}
