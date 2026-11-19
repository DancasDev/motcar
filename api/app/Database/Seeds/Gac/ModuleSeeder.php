<?php

namespace App\Database\Seeds\Gac;

use CodeIgniter\Database\Seeder;

class ModuleSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('gac_module');
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

        echo "  Modulos insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getCategories(): array {
        $result = $this->db->table('gac_module_category')
            ->select('id, code')
            ->get()
            ->getResultArray();
        return array_column($result, 'id', 'code');
    }

    private function getBaseData(): array {
        $cats = $this->getCategories();
        $access  = $cats['access'] ?? null;
        $company = $cats['company'] ?? null;
        $system  = $cats['system'] ?? null;
        $storage = $cats['storage'] ?? null;
        $my      = $cats['my'] ?? null;
        if (!$access || !$system) return [];

        $result = [];

        // ---------------------------------------------------------------
        //  Dominio Access (acc_*)
        // ---------------------------------------------------------------
        $result[] = [
            'module_category_id' => $access,
            'code'               => 'acc_users',
            'name'               => ['es' => 'Usuarios', 'en' => 'Users'],
            'description'        => ['es' => 'Gestionar los usuarios que acceden al sistema.', 'en' => 'Manage users who access the system.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];
        $result[] = [
            'module_category_id' => $access,
            'code'               => 'acc_user_roles',
            'name'               => ['es' => 'Roles de usuario', 'en' => 'User Roles'],
            'description'        => ['es' => 'Asignar roles a los usuarios del sistema.', 'en' => 'Assign roles to system users.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];
        $result[] = [
            'module_category_id' => $access,
            'code'               => 'acc_user_permissions',
            'name'               => ['es' => 'Permisos de usuario', 'en' => 'User Permissions'],
            'description'        => ['es' => 'Configurar permisos y accesos individuales para cada usuario.', 'en' => 'Configure individual permissions and access for each user.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];
        $result[] = [
            'module_category_id' => $access,
            'code'               => 'acc_roles',
            'name'               => ['es' => 'Roles', 'en' => 'Roles'],
            'description'        => ['es' => 'Gestionar los roles que agrupan permisos en el sistema.', 'en' => 'Manage roles that group permissions in the system.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];
        $result[] = [
            'module_category_id' => $access,
            'code'               => 'acc_role_members',
            'name'               => ['es' => 'Miembros del rol', 'en' => 'Role Members'],
            'description'        => ['es' => 'Consultar qué usuarios o sistemas tienen asignado cada rol.', 'en' => 'View which users or systems are assigned to each role.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];
        $result[] = [
            'module_category_id' => $access,
            'code'               => 'acc_role_permissions',
            'name'               => ['es' => 'Permisos de rol', 'en' => 'Role Permissions'],
            'description'        => ['es' => 'Configurar los permisos y accesos correspondientes a cada rol.', 'en' => 'Configure permissions and access assigned to each role.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];
        $result[] = [
            'module_category_id' => $access,
            'code'               => 'acc_clients',
            'name'               => ['es' => 'Sistemas externos', 'en' => 'External Systems'],
            'description'        => ['es' => 'Gestionar aplicaciones o sistemas externos que se conectan al sistema.', 'en' => 'Manage external applications or systems connecting to the system.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];
        $result[] = [
            'module_category_id' => $access,
            'code'               => 'acc_client_roles',
            'name'               => ['es' => 'Roles de sistemas externos', 'en' => 'External System Roles'],
            'description'        => ['es' => 'Asignar roles a las aplicaciones o sistemas externos.', 'en' => 'Assign roles to external applications or systems.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];
        $result[] = [
            'module_category_id' => $access,
            'code'               => 'acc_client_permissions',
            'name'               => ['es' => 'Permisos de sistemas externos', 'en' => 'External System Permissions'],
            'description'        => ['es' => 'Configurar los permisos y accesos de cada aplicación o sistema externo.', 'en' => 'Configure permissions and access for each external application or system.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];
        $result[] = [
            'module_category_id' => $access,
            'code'               => 'acc_modules',
            'name'               => ['es' => 'Módulos', 'en' => 'Modules'],
            'description'        => ['es' => 'Gestionar los módulos y funciones habilitadas en el sistema.', 'en' => 'Manage modules and functions enabled in the system.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];

        // ---------------------------------------------------------------
        //  Dominio Company (cmp_*)
        // ---------------------------------------------------------------
        if ($company) {
            $result[] = [
                'module_category_id' => $company,
                'code'               => 'cmp_branches',
                'name'               => ['es' => 'Sucursales', 'en' => 'Branches'],
                'description'        => ['es' => 'Gestionar las sucursales de la empresa.', 'en' => 'Manage the company branches.'],
                'is_developing'      => '0',
                'scope'              => '1',
                'payload_schema'     => null,
            ];
        }

        // ---------------------------------------------------------------
        //  Dominio System (sys_*)
        // ---------------------------------------------------------------
        $result[] = [
            'module_category_id' => $system,
            'code'               => 'sys_binnacles',
            'name'               => ['es' => 'Historial de cambios', 'en' => 'Change History'],
            'description'        => ['es' => 'Consultar el registro de eventos y cambios realizados en el sistema.', 'en' => 'View the log of events and changes made in the system.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];
        $result[] = [
            'module_category_id' => $system,
            'code'               => 'sys_metadata',
            'name'               => ['es' => 'Metadatos del sistema', 'en' => 'System Metadata'],
            'description'        => ['es' => 'Configurar metadatos y campos dinámicos en el sistema.', 'en' => 'Configure metadata and dynamic fields in the system.'],
            'is_developing'      => '0',
            'scope'              => '0',
            'payload_schema'     => null,
        ];

        // ---------------------------------------------------------------
        //  Dominio Storage (stg_*)
        // ---------------------------------------------------------------
        if ($storage) {
            $result[] = [
                'module_category_id' => $storage,
                'code'               => 'stg_files',
                'name'               => ['es' => 'Archivos', 'en' => 'Files'],
                'description'        => ['es' => 'Subir, consultar y descargar archivos en el sistema.', 'en' => 'Upload, view, and download files in the system.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => '{"lck_ids":{"type":["array", "NULL"], "rules": {"item_type": "int"}, "default": null}}',
            ];
            $result[] = [
                'module_category_id' => $storage,
                'code'               => 'stg_admin',
                'name'               => ['es' => 'Administración de archivos', 'en' => 'Storage Admin'],
                'description'        => ['es' => 'Gestionar los contenedores y el almacenamiento de archivos del sistema.', 'en' => 'Manage containers and file storage in the system.'],
                'is_developing'      => '0',
                'scope'              => '0',
                'payload_schema'     => null,
            ];
        }

        // ---------------------------------------------------------------
        //  Dominio Personal (my_*)
        // ---------------------------------------------------------------
        if ($my) {
            $result[] = [
                'module_category_id' => $my,
                'code'               => 'my_profile',
                'name'               => ['es' => 'Mi perfil', 'en' => 'My Profile'],
                'description'        => ['es' => 'Ver y actualizar la información personal y credenciales propias.', 'en' => 'View and update own personal information and credentials.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ];
            $result[] = [
                'module_category_id' => $my,
                'code'               => 'my_binnacles',
                'name'               => ['es' => 'Mi actividad', 'en' => 'My Activity'],
                'description'        => ['es' => 'Consultar el historial de acciones y cambios realizados por mi cuenta.', 'en' => 'View the history of actions and changes made by my account.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ];
        }

        return $result;
    }

    private function getCustomData(): array {
        $cats = $this->getCategories();
        $finance    = $cats['finance'] ?? null;
        $accounting = $cats['accounting'] ?? null;
        if (!$finance || !$accounting) return [];

        return [
            [
                'module_category_id' => $finance,
                'code'               => 'glb_persons',
                'name'               => ['es' => 'Personas', 'en' => 'Persons'],
                'description'        => ['es' => 'Directorio unificado de clientes y asesores.', 'en' => 'Unified directory of clients and advisors.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $finance,
                'code'               => 'glb_note_categories',
                'name'               => ['es' => 'Categorías de Notas CRM', 'en' => 'CRM Note Categories'],
                'description'        => ['es' => 'Clasificación de notas de seguimiento y promesas de pago.', 'en' => 'Classification of CRM notes and payment promises.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $accounting,
                'code'               => 'acc_currencies',
                'name'               => ['es' => 'Monedas', 'en' => 'Currencies'],
                'description'        => ['es' => 'Catálogo de divisas e instrumentos monetarios.', 'en' => 'Currency catalog and monetary instruments.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $accounting,
                'code'               => 'acc_payment_account_categories',
                'name'               => ['es' => 'Categorías de Cuentas de Pago', 'en' => 'Payment Account Categories'],
                'description'        => ['es' => 'Tipos de canales receptores (efectivo, bancos, billeteras).', 'en' => 'Types of receiving channels (cash, banks, wallets).'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $accounting,
                'code'               => 'acc_payment_accounts',
                'name'               => ['es' => 'Cuentas Receptoras de Pago', 'en' => 'Payment Accounts'],
                'description'        => ['es' => 'Cajas físicas y cuentas bancarias receptoras de cobros.', 'en' => 'Physical registers and bank accounts receiving payments.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $accounting,
                'code'               => 'acc_receivable_concepts',
                'name'               => ['es' => 'Conceptos de Cobro', 'en' => 'Receivable Concepts'],
                'description'        => ['es' => 'Catálogo de conceptos y plantillas de cuentas por cobrar.', 'en' => 'Catalog of concepts and receivable templates.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $accounting,
                'code'               => 'acc_exchange_rates',
                'name'               => ['es' => 'Tasas de Cambio', 'en' => 'Exchange Rates'],
                'description'        => ['es' => 'Historial y registro de tasas de cambio diarias.', 'en' => 'History and daily exchange rate entries.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $finance,
                'code'               => 'fnc_san_groups',
                'name'               => ['es' => 'Grupos SAM', 'en' => 'SAM Groups'],
                'description'        => ['es' => 'Círculos y grupos de ahorro y adjudicación colectiva.', 'en' => 'Collective savings and vehicle allocation circles.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $finance,
                'code'               => 'fnc_san_participants',
                'name'               => ['es' => 'Participantes SAM', 'en' => 'SAM Participants'],
                'description'        => ['es' => 'Suscripciones y cupos asignados a personas en grupos SAM.', 'en' => 'Subscriptions and assigned quotas in SAM groups.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $finance,
                'code'               => 'fnc_contract_documents',
                'name'               => ['es' => 'Contratos SAM', 'en' => 'SAM Contracts'],
                'description'        => ['es' => 'Documentos contractuales y formalización de compromisos de pago.', 'en' => 'Contractual documents and formalization of payment commitments.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $accounting,
                'code'               => 'acc_receivables',
                'name'               => ['es' => 'Cuentas por Cobrar', 'en' => 'Receivables'],
                'description'        => ['es' => 'Cartera de cuotas, cuotas vencidas y obligaciones por cobrar.', 'en' => 'Receivable installments, overdue installments and obligations.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $accounting,
                'code'               => 'acc_receipts',
                'name'               => ['es' => 'Recibos de Cobro', 'en' => 'Collection Receipts'],
                'description'        => ['es' => 'Comprobantes de recaudación de caja e imputación en cascada.', 'en' => 'Cash collection receipts and cascade amortizations.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $accounting,
                'code'               => 'acc_client_credits',
                'name'               => ['es' => 'Saldos a Favor', 'en' => 'Client Credits'],
                'description'        => ['es' => 'Saldos a favor y excedentes de pagos de clientes.', 'en' => 'Client credits and overpayment balances.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
            [
                'module_category_id' => $finance,
                'code'               => 'glb_notes',
                'name'               => ['es' => 'Notas CRM', 'en' => 'CRM Notes'],
                'description'        => ['es' => 'Bitácora de seguimiento, acuerdos y notas operativas.', 'en' => 'CRM follow-up logs, agreements and operational notes.'],
                'is_developing'      => '0',
                'scope'              => '0,1',
                'payload_schema'     => null,
            ],
        ];
    }
}
