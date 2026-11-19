<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

class StorageSync extends BaseCommand {
    protected $group       = 'Storage';
    protected $name        = 'storage:sync';
    protected $description = 'Sincroniza y crea de forma idempotente las carpetas/buckets de almacenamiento para los lockers registrados.';
    protected $usage       = 'storage:sync';

    public function run(array $params) {
        CLI::write('=== Sincronizando Lockers de Almacenamiento ===', 'yellow');

        $config = config('Storage');
        $driver = $config->driver ?? 'local';

        // 1. Obtener lockers activos de la BD
        $db = \Config\Database::connect();
        $lockers = $db->table('sys_storage_locker')
            ->select('id, locker_key, name, is_private, optimize_file, max_size')
            ->where('is_disabled', '0')
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($lockers)) {
            CLI::write('No se encontraron lockers activos en la base de datos.', 'light_gray');
            return;
        }

        CLI::write('Driver configurado: ' . strtoupper($driver), 'white');
        CLI::write('Lockers encontrados en BD: ' . count($lockers), 'white');
        CLI::newLine();

        $rows = [];
        $totalCreated  = 0;
        $totalExisting = 0;
        $totalErrors   = 0;

        foreach ($lockers as $locker) {
            $isPrivate = ($locker['is_private'] ?? '0') === '1';
            $visLabel  = $isPrivate ? 'Privado' : 'Público';
            $lockerKey = $locker['locker_key'];

            try {
                $filesystem = Services::storage($isPrivate);
                $exists     = $filesystem->directoryExists($lockerKey);

                if ($exists) {
                    $status = CLI::color('EXISTENTE', 'light_gray');
                    $totalExisting++;
                } else {
                    $filesystem->createDirectory($lockerKey);
                    $status = CLI::color('CREADO', 'green');
                    $totalCreated++;
                }

                $location = $this->resolveLocation($config, $driver, $lockerKey, $isPrivate);

                $rows[] = [
                    $lockerKey,
                    $visLabel,
                    strtoupper($driver),
                    $location,
                    $status,
                ];
            } catch (\Throwable $e) {
                $status = CLI::color('ERROR', 'red');
                $totalErrors++;
                $rows[] = [
                    $lockerKey,
                    $visLabel,
                    strtoupper($driver),
                    $e->getMessage(),
                    $status,
                ];
            }
        }

        CLI::table($rows, ['Locker Key', 'Tipo', 'Driver', 'Ubicación / Prefijo', 'Estado']);
        CLI::newLine();

        CLI::write("Resumen: {$totalCreated} creados, {$totalExisting} ya existentes, {$totalErrors} errores.", $totalErrors > 0 ? 'red' : 'green');
    }

    private function resolveLocation($config, string $driver, string $lockerKey, bool $isPrivate): string {
        if ($driver === 's3') {
            $prefix = trim($config->s3Prefix ?: 'uploads/', '/');
            return "s3://{$config->s3Bucket}/{$prefix}/{$lockerKey}/";
        }

        $root = $isPrivate
            ? ($config->localPrivateRoot ?: WRITEPATH . 'uploads/')
            : ($config->localPublicRoot ?: FCPATH . 'uploads/');

        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $lockerKey);
    }
}
