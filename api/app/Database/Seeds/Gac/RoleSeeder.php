<?php

namespace App\Database\Seeds\Gac;

use CodeIgniter\Database\Seeder;

class RoleSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('gac_role');
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

        echo "  Roles insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [
            [
                'code'        => 'admin',
                'name'        => ['es' => 'Administrador de sistema', 'en' => 'System Administrator'],
                'description' => ['es' => 'Acceso total a todas las funciones y configuraciones del sistema.', 'en' => 'Full access to all system features and configurations.'],
            ],
            [
                'code'        => 'supervisor',
                'name'        => ['es' => 'Supervisor de sistema', 'en' => 'System Supervisor'],
                'description' => ['es' => 'Supervisión y lectura operativa del sistema.', 'en' => 'System supervision and operational reading.'],
            ],
        ];
    }

    private function getCustomData(): array {
        return [
            [
                'code'        => 'gerente_sucursal',
                'name'        => ['es' => 'Gerente de sucursal', 'en' => 'Branch Manager'],
                'description' => ['es' => 'Supervisión y control operativo de la sucursal, adjudicación y anulación de comprobantes.', 'en' => 'Branch operational supervision, allocation and receipt voiding.'],
            ],
            [
                'code'        => 'cajero',
                'name'        => ['es' => 'Cajero / Recaudador', 'en' => 'Cashier / Collector'],
                'description' => ['es' => 'Emisión de recibos de cobro, medios de pago y consulta de obligaciones y saldos.', 'en' => 'Collection receipt issuance, payments, and obligation and credit consultation.'],
            ],
            [
                'code'        => 'asesor_comercial',
                'name'        => ['es' => 'Asesor comercial', 'en' => 'Sales Advisor'],
                'description' => ['es' => 'Atención a clientes, suscripción de participantes en grupos SAM y seguimiento comercial.', 'en' => 'Customer service, SAM participant subscriptions, and CRM follow-up.'],
            ],
            [
                'code'        => 'analista_cobranzas',
                'name'        => ['es' => 'Analista de cobranzas', 'en' => 'Collections Analyst'],
                'description' => ['es' => 'Gestión de cartera, sincronización de morosidad, acuerdos de pago y seguimiento de deudas.', 'en' => 'Portfolio management, overdue synchronization, payment agreements, and debt tracking.'],
            ],
        ];
    }
}
