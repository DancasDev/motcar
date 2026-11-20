<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropInitialDueDateFromFncFinancing extends Migration {
    public function up(): void {
        if ($this->db->fieldExists('initial_due_date', 'fnc_financing')) {
            $this->forge->dropColumn('fnc_financing', 'initial_due_date');
        }
    }

    public function down(): void {
        $this->forge->addColumn('fnc_financing', [
            'initial_due_date' => [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'installment_amount',
            ],
        ]);
    }
}
