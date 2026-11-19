<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlbMetadataFieldType extends Migration
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
                'comment'    => 'Código identificador del tipo de campo (ej: text, number, select)',
            ],
            'name' => [
                'type'    => 'JSON',
                'comment' => 'Nombre del tipo de campo en formato i18n',
            ],
            'description' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Descripción del tipo de campo en formato i18n',
            ],
            'attribute_schema' => [
                'type'    => 'JSON',
                'comment' => 'Esquemas para los atributos del campo',
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

        $this->forge->createTable('glb_metadata_field_type', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('glb_metadata_field_type');
    }
}
