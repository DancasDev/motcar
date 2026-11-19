<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFncContractType extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'scope' => [
                'type'       => 'ENUM',
                'constraint' => ['0'],
                'default'    => '0',
                'comment'    => '0 = Participante SAN',
            ],
            'metadata_schema_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
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
        $this->forge->addKey('metadata_schema_id');

        $this->forge->createTable('fnc_contract_type', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Modelos y plantillas juridicas de contrato'",
        ]);

        $this->forge->addForeignKey('metadata_schema_id', 'glb_metadata_schema', 'id', 'SET NULL', 'RESTRICT', 'fk_fnc_ct_metadata_schema_id');
        $this->forge->processIndexes('fnc_contract_type');
    }

    public function down(): void {
        $this->forge->dropForeignKey('fnc_contract_type', 'fk_fnc_ct_metadata_schema_id');
        $this->forge->dropTable('fnc_contract_type');
    }
}
