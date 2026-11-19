<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFncContractDocument extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'branch_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'contract_type_id' => [
                'type'     => 'INT',
                'unsigned' => true,
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
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'issue_date' => [
                'type' => 'DATETIME',
            ],
            'signed_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'file_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'sign_status' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2'],
                'default'    => '0',
                'comment'    => '0=Pendiente, 1=Firmado, 2=Rechazado/Rescindido',
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
        $this->forge->addKey('branch_id');
        $this->forge->addKey('contract_type_id');
        $this->forge->addKey(['origin_type', 'origin_id']);

        $this->forge->createTable('fnc_contract_document', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Instancias de contratos formalizados y firmados'",
        ]);

        $this->forge->addForeignKey('branch_id', 'glb_branch', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_cd_branch_id');
        $this->forge->addForeignKey('contract_type_id', 'fnc_contract_type', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_cd_contract_type_id');
        $this->forge->processIndexes('fnc_contract_document');
    }

    public function down(): void {
        $this->forge->dropForeignKey('fnc_contract_document', 'fk_fnc_cd_contract_type_id');
        $this->forge->dropForeignKey('fnc_contract_document', 'fk_fnc_cd_branch_id');
        $this->forge->dropTable('fnc_contract_document');
    }
}
