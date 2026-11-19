<?php

namespace App\Database\Seeds\Global;

use CodeIgniter\Database\Seeder;
use App\Models\Global\BranchModel;

class BranchSeeder extends Seeder {
    public function run(): void {
        $builder  = $this->db->table('glb_branch');
        $time     = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated  = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            $parentId = $row['parent_id'] ?? null;
            $name     = trim($row['name']);

            $query = $builder->select('id')
                ->where('name', $name)
                ->limit(1);

            if ($parentId === null) {
                $query->where('parent_id IS NULL');
            } else {
                $query->where('parent_id', $parentId);
            }

            $existing = $query->get()->getRow();

            if ($existing) {
                $row['updated_at'] = $time;
                $row['deleted_at'] = null;
                $builder->where('id', $existing->id)->update($row);
                $updated++;
            } else {
                $row['created_at'] = $time;
                $builder->insert($row);
                $inserted++;
            }
        }

        (new BranchModel())->removeMapByCache();

        echo "  Sucursales insertadas: {$inserted}, actualizadas: {$updated}" . PHP_EOL;
    }

    /**
     * Datos base del CORE del boilerplate.
     */
    private function getBaseData(): array {
        return [];
    }

    /**
     * Datos personalizados del FORK (Motcar).
     */
    private function getCustomData(): array {
        return [
            [
                'parent_id'   => null,
                'name'        => 'San Francisco',
                'is_disabled' => '0',
            ],
        ];
    }
}
