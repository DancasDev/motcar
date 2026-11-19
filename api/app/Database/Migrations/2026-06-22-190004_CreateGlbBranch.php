<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlbBranch extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'parent_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'comment'  => 'Sucursal padre; NULL = raíz',
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
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
        $this->forge->addUniqueKey(['parent_id', 'name']);
        $this->forge->addKey('parent_id');

        $this->forge->createTable('glb_branch', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Sucursales de la empresa (arbol jerarquico)'",
        ]);

        $this->forge->addForeignKey('parent_id', 'glb_branch', 'id', 'RESTRICT', 'RESTRICT', 'fk_glb_branch_parent_id');
        $this->forge->processIndexes('glb_branch');
    }

    public function down()
    {
        $this->forge->dropForeignKey('glb_branch', 'fk_glb_branch_parent_id');
        $this->forge->dropTable('glb_branch');
    }
}
