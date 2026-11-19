<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlbMetadataSchemaField extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'metadata_schema_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'comment'  => 'Esquema al que pertenece el campo (glb_metadata_schema.id)',
            ],
            'metadata_schema_group_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'comment'  => 'Grupo al que pertenece el campo (glb_metadata_schema_group.id)',
            ],
            'metadata_field_type_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'comment'  => 'Tipo de campo (glb_metadata_field_type.id)',
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'comment'    => 'Nombre/clave del campo en el JSON de almacenamiento',
            ],
            'attribute_values' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Valores de configuración requeridos según el attribute_schema del tipo de campo',
            ],
            'rules' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Reglas de validación aplicables al campo',
            ],
            'position' => [
                'type'       => 'TINYINT',
                'unsigned'   => true,
                'default'    => 0,
                'comment'    => 'Orden de presentación en el formulario',
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
        $this->forge->addUniqueKey(['metadata_schema_id', 'code']);
        $this->forge->addKey('position');

        $this->forge->addForeignKey(
            'metadata_schema_id',
            'glb_metadata_schema',
            'id',
            'RESTRICT',
            'CASCADE',
            'fk_glb_metadata_schema_field_schema_id'
        );

        $this->forge->addForeignKey(
            'metadata_field_type_id',
            'glb_metadata_field_type',
            'id',
            'RESTRICT',
            'CASCADE',
            'fk_glb_metadata_schema_field_type_id'
        );

        $this->forge->addForeignKey(
            'metadata_schema_group_id',
            'glb_metadata_schema_group',
            'id',
            'SET NULL',
            'CASCADE',
            'fk_glb_metadata_schema_field_group_id'
        );

        $this->forge->createTable('glb_metadata_schema_field', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('glb_metadata_schema_field');
    }
}
