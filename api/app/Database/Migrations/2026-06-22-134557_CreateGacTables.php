<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use DancasDev\GAC\Schema;
use DancasDev\GAC\Drivers\Database\MysqliConnection;

class CreateGacTables extends Migration {
    public function up() {
        $conn = new MysqliConnection(db_connect()->connID);

        Schema::install($conn, [
            'show_comments' => true
        ]);
    }

    public function down() {
        $conn = new MysqliConnection(db_connect()->connID);

        Schema::uninstall($conn);
    }
}