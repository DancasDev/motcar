<?php

namespace App\Database\Seeds\Gac;

use CodeIgniter\Database\Seeder;

class ModuleCategorySeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('gac_module_category');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());

        foreach ($list as $row) {
            $row['name'] = json_encode($row['name'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $row['description'] = json_encode($row['description'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $existing = $builder->select('id')
                ->where('code', $row['code'])
                ->limit(1)
                ->get()
                ->getRow();

            if ($existing) {
                $row['updated_at'] = $time;
                $builder->where('id', $existing->id)->update($row);
                $updated++;
            } else {
                $row['created_at'] = $time;
                $builder->insert($row);
                $inserted++;
            }
        }

        echo "  Categorias de modulo insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [
            [
                'code'        => 'access',
                'name'        => ['es' => 'Control de Acceso', 'en' => 'Access Control'],
                'description' => ['es' => 'Administración de usuarios, roles y permisos del sistema.', 'en' => 'Management of system users, roles, and permissions.'],
                'scope'       => '0,1',
            ],
            [
                'code'        => 'company',
                'name'        => ['es' => 'Empresa', 'en' => 'Company'],
                'description' => ['es' => 'Gestión de la estructura y sucursales de la empresa.', 'en' => 'Management of company structure and branches.'],
                'scope'       => '1',
            ],
            [
                'code'        => 'system',
                'name'        => ['es' => 'Sistema', 'en' => 'System'],
                'description' => ['es' => 'Historial de cambios, metadatos y configuración de la plataforma.', 'en' => 'Change log, metadata, and platform configuration.'],
                'scope'       => '0,1',
            ],
            [
                'code'        => 'storage',
                'name'        => ['es' => 'Almacenamiento', 'en' => 'Storage'],
                'description' => ['es' => 'Subida, consulta y administración de archivos del sistema.', 'en' => 'Upload, view, and manage system files.'],
                'scope'       => '0,1',
            ],
            [
                'code'        => 'my',
                'name'        => ['es' => 'Personal', 'en' => 'Personal'],
                'description' => ['es' => 'Información personal, credenciales y actividad propia del usuario.', 'en' => 'Personal information, credentials, and user activity.'],
                'scope'       => '0,1',
            ],
        ];
    }

    private function getCustomData(): array {
        return [
            [
                'code'        => 'finance',
                'name'        => ['es' => 'Finanzas / SAN', 'en' => 'Finance / SAN'],
                'description' => ['es' => 'Módulos operativos del sistema de financiamiento y ahorro.', 'en' => 'Financing and savings operational modules.'],
                'scope'       => '0,1',
            ],
            [
                'code'        => 'accounting',
                'name'        => ['es' => 'Contabilidad / Tesorería', 'en' => 'Accounting / Treasury'],
                'description' => ['es' => 'Cuentas de cobro, tasas, cajas y medios de liquidación.', 'en' => 'Receivables, rates, cash registers, and payment accounts.'],
                'scope'       => '0,1',
            ],
        ];
    }
}
