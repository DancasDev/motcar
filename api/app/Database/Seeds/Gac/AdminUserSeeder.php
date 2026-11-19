<?php

namespace App\Database\Seeds\Gac;

use CodeIgniter\Database\Seeder;
use App\Models\Gac\UserModel;
use App\Models\Gac\RoleEntityModel;
use App\Models\System\BinnacleModel;

class AdminUserSeeder extends Seeder {
    public function run(): void {
        $db = $this->db;

        // 1. Obtener el rol "admin"
        $adminRole = $db->table('gac_role')
            ->select('id')
            ->where('code', 'admin')
            ->where('deleted_at', null)
            ->limit(1)
            ->get()
            ->getRow();

        if (!$adminRole) {
            echo '  [ERROR] No se encontro el rol "admin". Ejecute RoleSeeder primero.' . PHP_EOL;
            return;
        }

        // 2. Verificar idempotencia: ¿Existe ya algún usuario activo con este rol?
        $existingAdmin = $db->table('gac_role_entity')
            ->join('gac_user', 'gac_user.id = gac_role_entity.entity_id')
            ->where('gac_role_entity.role_id', $adminRole->id)
            ->where('gac_role_entity.entity_type', '1')
            ->where('gac_role_entity.is_disabled', '0')
            ->where('gac_role_entity.deleted_at', null)
            ->where('gac_user.deleted_at', null)
            ->limit(1)
            ->get()
            ->getRow();

        if ($existingAdmin) {
            echo '  [INFO] Administrador de sistema ya existe en la base de datos. Omitiendo creacion.' . PHP_EOL;
            return;
        }

        // 3. Generar credenciales iniciales de alta entropía
        $username = 'aDmin_' . bin2hex(random_bytes(3));
        $password = $this->generateSecurePassword(16);
        $email    = strtolower($username) . '@local.dev';

        // Obtener idioma por defecto (es)
        $language = $db->table('glb_language')
            ->select('id')
            ->where('code', 'es')
            ->where('is_disabled', '0')
            ->where('deleted_at', null)
            ->limit(1)
            ->get()
            ->getRow();
        $languageId = $language ? (int) $language->id : null;

        // 4. Crear el usuario con force_to = '0' (cambio obligatorio de contraseña)
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'username'    => $username,
            'password'    => $password,
            'email'       => $email,
            'first_name'  => 'Super',
            'last_name'   => 'Admin',
            'force_to'    => '0',
            'language_id' => $languageId,
            'is_disabled' => '0',
        ]);

        if ($userId === false) {
            echo '  [ERROR] No se pudo crear el usuario administrador: '
                . json_encode($userModel->errors(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
            return;
        }

        // 5. Asignar el rol de administrador en gac_role_entity
        $roleEntityModel = new RoleEntityModel();
        $roleEntityModel->skipValidation(true)->protect(false)->insert([
            'role_id'     => $adminRole->id,
            'entity_type' => '1',
            'entity_id'   => $userId,
            'priority'    => '0',
            'is_disabled' => '0',
        ]);

        // 6. Registrar en bitácora
        (new BinnacleModel())->log(
            'system',
            null,
            'gac_user',
            (string) $userId,
            'create',
            date('Y-m-d H:i:s'),
            '127.0.0.1',
            [
                'username' => $username,
                'role_id'  => $adminRole->id,
            ]
        );

        // 7. Mostrar credenciales efímeras en la terminal
        echo PHP_EOL;
        echo '  ======================================================================' . PHP_EOL;
        echo '  [BOOTSTRAP] CREDENCIALES DE ADMINISTRADOR INICIAL GENERADAS' . PHP_EOL;
        echo '  ======================================================================' . PHP_EOL;
        echo "    Username:          {$username}" . PHP_EOL;
        echo "    Email:             {$email}" . PHP_EOL;
        echo "    Password temporal: {$password}" . PHP_EOL;
        echo PHP_EOL;
        echo '    AVISO: Es OBLIGATORIO cambiar esta contrasena en el primer inicio' . PHP_EOL;
        echo '           de sesion (force_to = 0). Guarde estas credenciales.' . PHP_EOL;
        echo '  ======================================================================' . PHP_EOL;
        echo PHP_EOL;
    }

    private function generateSecurePassword(int $length = 16): string {
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower   = 'abcdefghijkmnopqrstuvwxyz';
        $digits  = '23456789';
        $special = '!@#$%^&*()_+[]{}|;:,.<>?-';
        $all     = $upper . $lower . $digits . $special;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $special[random_int(0, strlen($special) - 1)],
            $special[random_int(0, strlen($special) - 1)],
        ];

        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $chars[$i];
            $chars[$i] = $chars[$j];
            $chars[$j] = $tmp;
        }

        return implode('', $chars);
    }
}
