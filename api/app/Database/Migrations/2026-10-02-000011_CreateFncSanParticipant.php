<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFncSanParticipant extends Migration {
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
            'san_group_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'client_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'referrer_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'frequency_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'position' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'unsigned'   => true,
                'null'       => true,
            ],
            'frequency_base_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'installment_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'null'       => true,
            ],
            'installment_count' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'unsigned'   => true,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1', '2', '3'],
                'default'    => '0',
                'comment'    => '0=En espera, 1=Activo, 2=Retirado, 3=Culminado',
            ],
            'is_overdue' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
            ],
            'awarded_at' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'benefit_metadata' => [
                'type'    => 'LONGTEXT',
                'null'    => true,
                'comment' => 'JSON para especificaciones del beneficio adjudicado',
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
        $this->forge->addUniqueKey(['san_group_id', 'position']);
        $this->forge->addKey('branch_id');
        $this->forge->addKey('san_group_id');
        $this->forge->addKey('client_id');
        $this->forge->addKey('referrer_id');
        $this->forge->addKey('frequency_id');
        $this->forge->addKey('awarded_at');

        $this->forge->createTable('fnc_san_participant', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Suscripciones de personas a cupos de un grupo SAN'",
        ]);

        $this->forge->addForeignKey('branch_id', 'glb_branch', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_sp_branch_id');
        $this->forge->addForeignKey('san_group_id', 'fnc_san_group', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_sp_san_group_id');
        $this->forge->addForeignKey('client_id', 'glb_person', 'id', 'RESTRICT', 'RESTRICT', 'fk_fnc_sp_client_id');
        $this->forge->addForeignKey('referrer_id', 'glb_person', 'id', 'SET NULL', 'RESTRICT', 'fk_fnc_sp_referrer_id');
        $this->forge->addForeignKey('frequency_id', 'fnc_frequency', 'id', 'SET NULL', 'RESTRICT', 'fk_fnc_sp_frequency_id');
        $this->forge->processIndexes('fnc_san_participant');
    }

    public function down(): void {
        $this->forge->dropForeignKey('fnc_san_participant', 'fk_fnc_sp_frequency_id');
        $this->forge->dropForeignKey('fnc_san_participant', 'fk_fnc_sp_referrer_id');
        $this->forge->dropForeignKey('fnc_san_participant', 'fk_fnc_sp_client_id');
        $this->forge->dropForeignKey('fnc_san_participant', 'fk_fnc_sp_san_group_id');
        $this->forge->dropForeignKey('fnc_san_participant', 'fk_fnc_sp_branch_id');
        $this->forge->dropTable('fnc_san_participant');
    }
}
