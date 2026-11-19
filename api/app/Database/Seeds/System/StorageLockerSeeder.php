<?php

namespace App\Database\Seeds\System;

use CodeIgniter\Database\Seeder;

class StorageLockerSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('sys_storage_locker');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());
        foreach ($list as $row) {
            $row['name'] = json_encode($row['name'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $row['description'] = json_encode($row['description'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $existing = $builder->select('id, is_private')
                ->where('locker_key', $row['locker_key'])
                ->limit(1)
                ->get()
                ->getRow();

            if ($existing) {
                // Proteger inmutabilidad de is_private tras creación
                unset($row['is_private']);
                $row['updated_at'] = $time;
                $builder->where('id', $existing->id)->update($row);
                $updated++;
            } else {
                $row['created_at'] = $time;
                $builder->insert($row);
                $inserted++;
            }
        }

        echo "  Lockers de almacenamiento insertados: {$inserted}, actualizados: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [
            [
                'locker_key'    => 'images/users/profiles',
                'name'          => ['es' => 'Fotos de perfil de usuarios', 'en' => 'User profile photos'],
                'description'   => ['es' => 'Contenedor público para imágenes de avatar y perfil.', 'en' => 'Public container for avatar and profile images.'],
                'allowed_mimes' => 'image/jpeg,image/png,image/webp',
                'max_size'      => 5120, // 5 MB
                'optimize_file' => '1',
                'is_private'    => '0',
                'is_disabled'   => '0',
            ],
            [
                'locker_key'    => 'images/users/signatures',
                'name'          => ['es' => 'Firmas digitalizadas de usuarios', 'en' => 'User digital signatures'],
                'description'   => ['es' => 'Contenedor privado para firmas manuscritas digitalizadas.', 'en' => 'Private container for digitized handwritten signatures.'],
                'allowed_mimes' => 'image/png',
                'max_size'      => 2048, // 2 MB
                'optimize_file' => '0',
                'is_private'    => '1',
                'is_disabled'   => '0',
            ],
        ];
    }

    private function getCustomData(): array {
        return [
            [
                'locker_key'    => 'documents/contracts/signed',
                'name'          => ['es' => 'Contratos firmados', 'en' => 'Signed contracts'],
                'description'   => ['es' => 'Archivos escaneados o fotografías de contratos firmados físicamente.', 'en' => 'Scanned files or photos of physically signed contracts.'],
                'allowed_mimes' => 'application/pdf,image/jpeg,image/png,image/webp',
                'max_size'      => 10240, // 10 MB
                'optimize_file' => '0',
                'is_private'    => '1',
                'is_disabled'   => '0',
            ],
            [
                'locker_key'    => 'images/contracts/deliveries',
                'name'          => ['es' => 'Fotografías de entrega de motos', 'en' => 'Motorcycle delivery photos'],
                'description'   => ['es' => 'Fotografías de las motocicletas entregadas a los asociados.', 'en' => 'Photographs of motorcycles delivered to associates.'],
                'allowed_mimes' => 'image/jpeg,image/png,image/webp',
                'max_size'      => 5120, // 5 MB
                'optimize_file' => '1',
                'is_private'    => '0',
                'is_disabled'   => '0',
            ],
        ];
    }
}
