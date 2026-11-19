<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlbMetadataSchema extends Migration
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
                'constraint' => 60,
                'comment'    => 'Identificador único del esquema (ej: gac_user, product_clothing)',
            ],
            'table_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'comment'    => 'Tabla de la base de datos donde reside el registro físico (ej: gac_user, shop_product)',
            ],
            'storage_column' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => 'metadata',
                'comment'    => 'Columna en la tabla donde se almacena el JSON',
            ],
            'unique_keys' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Arreglo de combinaciones de campos que deben ser únicos (simples o compuestas)',
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
        $this->forge->addKey('table_name');

        $this->forge->createTable('glb_metadata_schema', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('glb_metadata_schema');
    }
}
