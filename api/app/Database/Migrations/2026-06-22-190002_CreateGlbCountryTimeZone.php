<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGlbCountryTimeZone extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'country_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'time_zone_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'is_default' => [
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
        $this->forge->addUniqueKey(['country_id', 'time_zone_id']);

        $this->forge->createTable('glb_country_time_zone', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
            'COMMENT' => "'Zonas horarias por pais'",
        ]);

        $this->forge->addForeignKey('country_id', 'glb_country', 'id', 'CASCADE', 'RESTRICT', 'fk_glb_country_time_zone_country_id');
        $this->forge->addForeignKey('time_zone_id', 'glb_time_zone', 'id', 'CASCADE', 'RESTRICT', 'fk_glb_country_time_zone_zone_id');
        $this->forge->processIndexes('glb_country_time_zone');
        // ponytail: enforce single default per country at app/seeder level
    }

    public function down()
    {
        $this->forge->dropForeignKey('glb_country_time_zone', 'fk_glb_country_time_zone_country_id');
        $this->forge->dropForeignKey('glb_country_time_zone', 'fk_glb_country_time_zone_zone_id');
        $this->forge->dropTable('glb_country_time_zone');
    }
}
