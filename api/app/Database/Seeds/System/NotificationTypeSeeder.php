<?php

namespace App\Database\Seeds\System;

use CodeIgniter\Database\Seeder;

class NotificationTypeSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('sys_notification_type');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        // Limpiar tipos obsoletos o ambiguos
        $builder->whereIn('code', ['security_alert', 'general_notification'])->delete();

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

        echo "  Tipos de notificacion insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [
            [
                'code'        => 'user_welcome',
                'name'        => ['es' => 'Bienvenida de usuario', 'en' => 'User Welcome'],
                'description' => ['es' => 'Notificación de bienvenida tras el registro o activación del usuario.', 'en' => 'Welcome notification after user registration or activation.'],
                'channels'    => '0',
                'action_url'  => null,
            ],
            [
                'code'        => 'user_locked',
                'name'        => ['es' => 'Bloqueo de cuenta', 'en' => 'Account Locked'],
                'description' => ['es' => 'Aviso de seguridad por bloqueo temporal tras superar intentos fallidos de acceso.', 'en' => 'Security notice due to temporary lockout after exceeding failed login attempts.'],
                'channels'    => '1,2',
                'action_url'  => '{app_url}/password/recovery',
            ],
            [
                'code'        => 'password_updated',
                'name'        => ['es' => 'Cambio de contraseña', 'en' => 'Password Changed'],
                'description' => ['es' => 'Aviso de seguridad tras la actualización de contraseña.', 'en' => 'Security notice after password update.'],
                'channels'    => '0,1,2',
                'action_url'  => '{app_url}/my/binnacles/{{binnacle_id}}',
            ],
        ];
    }

    private function getCustomData(): array {
        return [];
    }
}