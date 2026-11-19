<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccPaymentAccountCategory extends Migration {
    public function up(): void {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'metadata_schema_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
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
        $this->forge->addKey('metadata_schema_id');

        $this->forge->createTable('acc_payment_account_category', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Categorias y medios de pago/canales bancarios'",
        ]);

        $this->forge->addForeignKey('metadata_schema_id', 'glb_metadata_schema', 'id', 'SET NULL', 'RESTRICT', 'fk_acc_pac_metadata_schema_id');
        $this->forge->processIndexes('acc_payment_account_category');
    }

    public function down(): void {
        $this->forge->dropForeignKey('acc_payment_account_category', 'fk_acc_pac_metadata_schema_id');
        $this->forge->dropTable('acc_payment_account_category');
    }
}
