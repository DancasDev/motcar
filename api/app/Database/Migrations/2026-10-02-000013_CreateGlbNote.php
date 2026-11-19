<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlbNote extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'note_category_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'author_id' => [
                'type' => 'INT',
            ],
            'origin_type' => [
                'type'       => 'ENUM',
                'constraint' => ['0'],
                'default'    => '0',
                'comment'    => '0 = fnc_san_participant',
            ],
            'origin_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'content' => [
                'type' => 'TEXT',
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
        $this->forge->addKey('note_category_id');
        $this->forge->addKey('author_id');
        $this->forge->addKey(['origin_type', 'origin_id']);

        $this->forge->createTable('glb_note', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Bitacora CRM y notas de seguimiento a clientes'",
        ]);

        $this->forge->addForeignKey('note_category_id', 'glb_note_category', 'id', 'RESTRICT', 'RESTRICT', 'fk_glb_note_category_id');
        $this->forge->addForeignKey('author_id', 'gac_user', 'id', 'RESTRICT', 'RESTRICT', 'fk_glb_note_author_id');
        $this->forge->processIndexes('glb_note');
    }

    public function down(): void {
        $this->forge->dropForeignKey('glb_note', 'fk_glb_note_author_id');
        $this->forge->dropForeignKey('glb_note', 'fk_glb_note_category_id');
        $this->forge->dropTable('glb_note');
    }
}
