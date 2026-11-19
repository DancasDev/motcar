<?php

namespace App\Database\Seeds\Gac;

use CodeIgniter\Database\Seeder;

class PermissionSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('gac_permission');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());
        foreach ($list as $row) {
            $existing = $builder->select('id')
                ->where('module_id', $row['module_id'])
                ->where('entity_type', $row['entity_type'])
                ->where('entity_id', $row['entity_id'])
                ->where('scope_path', $row['scope_path'])
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

        echo "  Permisos insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getRoles(): array {
        $result = $this->db->table('gac_role')
            ->select('id, code')
            ->where('is_disabled', '0')
            ->get()
            ->getResultArray();
        return array_column($result, 'id', 'code');
    }

    private function getModules(): array {
        $result = $this->db->table('gac_module')
            ->select('id')
            ->where('is_disabled', '0')
            ->get()
            ->getResultArray();
        return $result;
    }

    private function getBaseData(): array {
        $roles = $this->getRoles();
        $modules = $this->getModules();
        $result = [];

        $adminId = $roles['admin'] ?? null;
        if ($adminId) {
            foreach ($modules as $m) {
                $result[] = [
                    'module_id' => $m['id'], 'entity_type' => '0', 'entity_id' => $adminId,
                    'scope_path' => '*', 'feature' => 63, 'level' => '2', 'is_disabled' => '0',
                ];
            }
        }

        $supervisorId = $roles['supervisor'] ?? null;
        if ($supervisorId) {
            foreach ($modules as $m) {
                $result[] = [
                    'module_id' => $m['id'], 'entity_type' => '0', 'entity_id' => $supervisorId,
                    'scope_path' => '*', 'feature' => 18, 'level' => '2', 'is_disabled' => '0',
                ];
            }
        }

        return $result;
    }

    private function getModulesByCode(): array {
        $result = $this->db->table('gac_module')
            ->select('id, code')
            ->where('is_disabled', '0')
            ->get()
            ->getResultArray();
        return array_column($result, 'id', 'code');
    }

    private function getCustomData(): array {
        $roles = $this->getRoles();
        $modules = $this->getModulesByCode();
        $result = [];

        // 1. Gerente de Sucursal (control operativo, supervisión, adjudicación y anulación)
        $managerId = $roles['gerente_sucursal'] ?? null;
        if ($managerId) {
            $managerMatrix = [
                'fnc_san_groups'          => ['feature' => 31, 'level' => '1'],
                'fnc_san_participants'    => ['feature' => 31, 'level' => '1'],
                'fnc_contract_documents'   => ['feature' => 31, 'level' => '1'],
                'glb_persons'             => ['feature' => 31, 'level' => '1'],
                'glb_notes'               => ['feature' => 31, 'level' => '1'],
                'glb_note_categories'    => ['feature' => 2,  'level' => '1'],
                'acc_receivables'         => ['feature' => 31, 'level' => '1'],
                'acc_receipts'            => ['feature' => 31, 'level' => '1'],
                'acc_client_credits'      => ['feature' => 6,  'level' => '1'],
                'acc_currencies'          => ['feature' => 2,  'level' => '1'],
                'acc_exchange_rates'      => ['feature' => 3,  'level' => '1'],
                'acc_payment_accounts'    => ['feature' => 2,  'level' => '1'],
                'acc_receivable_concepts' => ['feature' => 2,  'level' => '1'],
            ];
            foreach ($managerMatrix as $modCode => $cfg) {
                if (isset($modules[$modCode])) {
                    $result[] = [
                        'module_id'   => $modules[$modCode],
                        'entity_type' => '0',
                        'entity_id'   => $managerId,
                        'scope_path'  => '*',
                        'feature'     => $cfg['feature'],
                        'level'       => $cfg['level'],
                        'is_disabled' => '0',
                    ];
                }
            }
        }

        // 2. Cajero / Recaudador (emisión de recibos, medios de pago, consulta de cuentas y créditos)
        $cashierId = $roles['cajero'] ?? null;
        if ($cashierId) {
            $cashierMatrix = [
                'acc_receipts'         => ['feature' => 7, 'level' => '1'],
                'acc_receivables'      => ['feature' => 2, 'level' => '1'],
                'acc_client_credits'   => ['feature' => 2, 'level' => '1'],
                'glb_persons'          => ['feature' => 2, 'level' => '1'],
                'acc_currencies'       => ['feature' => 2, 'level' => '1'],
                'acc_exchange_rates'   => ['feature' => 2, 'level' => '1'],
                'acc_payment_accounts' => ['feature' => 2, 'level' => '1'],
            ];
            foreach ($cashierMatrix as $modCode => $cfg) {
                if (isset($modules[$modCode])) {
                    $result[] = [
                        'module_id'   => $modules[$modCode],
                        'entity_type' => '0',
                        'entity_id'   => $cashierId,
                        'scope_path'  => '*',
                        'feature'     => $cfg['feature'],
                        'level'       => $cfg['level'],
                        'is_disabled' => '0',
                    ];
                }
            }
        }

        // 3. Asesor Comercial (clientes, suscripciones SAM y seguimiento CRM)
        $advisorId = $roles['asesor_comercial'] ?? null;
        if ($advisorId) {
            $advisorMatrix = [
                'glb_persons'            => ['feature' => 15, 'level' => '1'],
                'fnc_san_groups'         => ['feature' => 2,  'level' => '1'],
                'fnc_san_participants'   => ['feature' => 3,  'level' => '1'],
                'fnc_contract_documents' => ['feature' => 7,  'level' => '1'],
                'glb_notes'              => ['feature' => 15, 'level' => '1'],
                'glb_note_categories'    => ['feature' => 2,  'level' => '1'],
                'acc_receivables'        => ['feature' => 2,  'level' => '1'],
            ];
            foreach ($advisorMatrix as $modCode => $cfg) {
                if (isset($modules[$modCode])) {
                    $result[] = [
                        'module_id'   => $modules[$modCode],
                        'entity_type' => '0',
                        'entity_id'   => $advisorId,
                        'scope_path'  => '*',
                        'feature'     => $cfg['feature'],
                        'level'       => $cfg['level'],
                        'is_disabled' => '0',
                    ];
                }
            }
        }

        // 4. Analista de Cobranzas (cartera, sincronización de morosidad y notas de acuerdo)
        $collectorId = $roles['analista_cobranzas'] ?? null;
        if ($collectorId) {
            $collectorMatrix = [
                'acc_receivables'        => ['feature' => 6,  'level' => '1'],
                'glb_notes'              => ['feature' => 15, 'level' => '1'],
                'glb_note_categories'    => ['feature' => 2,  'level' => '1'],
                'glb_persons'            => ['feature' => 2,  'level' => '1'],
                'fnc_san_participants'   => ['feature' => 2,  'level' => '1'],
                'fnc_contract_documents' => ['feature' => 2,  'level' => '1'],
                'fnc_san_groups'         => ['feature' => 2,  'level' => '1'],
                'acc_receipts'           => ['feature' => 2,  'level' => '1'],
                'acc_client_credits'     => ['feature' => 2,  'level' => '1'],
            ];
            foreach ($collectorMatrix as $modCode => $cfg) {
                if (isset($modules[$modCode])) {
                    $result[] = [
                        'module_id'   => $modules[$modCode],
                        'entity_type' => '0',
                        'entity_id'   => $collectorId,
                        'scope_path'  => '*',
                        'feature'     => $cfg['feature'],
                        'level'       => $cfg['level'],
                        'is_disabled' => '0',
                    ];
                }
            }
        }

        return $result;
    }
}
