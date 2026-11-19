<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlbMetadataSchemaGroup extends Migration
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
                'comment'  => 'Esquema al que pertenece el grupo (glb_metadata_schema.id)',
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'comment'    => 'Código único del grupo dentro del esquema (slug)',
            ],
            'title' => [
                'type'    => 'JSON',
                'comment' => 'Título del grupo en formato i18n JSON (ej: {"es":"Datos personales","en":"Personal info"})',
            ],
            'description' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Descripción opcional del grupo en formato i18n JSON',
            ],
            'position' => [
                'type'       => 'TINYINT',
                'unsigned'   => true,
                'default'    => 0,
                'comment'    => 'Orden visual de renderizado en el formulario',
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
            'fk_glb_metadata_schema_group_schema_id'
        );

        $this->forge->createTable('glb_metadata_schema_group', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('glb_metadata_schema_group');
    }
}
