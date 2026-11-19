<?php

namespace App\Commands;

use App\Models\System\NotificationModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Comando para purgar notificaciones antiguas de la tabla sys_notification.
 * Soporta ejecución puntual (cron OS) o periódica en segundo plano (--loop / daemon).
 */
class NotificationsPurge extends BaseCommand
{
    protected $group       = 'Notifications';
    protected $name        = 'notifications:purge';
    protected $description = 'Purga notificaciones antiguas (enviadas, leídas o DLQ) de la tabla sys_notification.';
    protected $usage       = 'notifications:purge [--days=28] [--batch=500] [--dry-run] [--loop] [--sleep=43200] [--iterations=N]';

    public function run(array $params)
    {
        $rawDays     = CLI::getOption('days') ?? ($params['days'] ?? null);
        $rawBatch    = CLI::getOption('batch') ?? ($params['batch'] ?? null);
        $dryRun      = CLI::getOption('dry-run') !== null || in_array('dry-run', $params, true) || in_array('--dry-run', $params, true);
        $loop        = CLI::getOption('loop') !== null || in_array('loop', $params, true) || in_array('--loop', $params, true);
        $rawSleep    = CLI::getOption('sleep') ?? ($params['sleep'] ?? null);
        $hasMaxIter  = CLI::getOption('iterations') !== null || isset($params['iterations']);
        $rawMaxIter  = CLI::getOption('iterations') ?? ($params['iterations'] ?? null);

        // Procesar argumentos CLI (compatibilidad con argv directo y llamadas programáticas)
        $rawArgs = array_merge($params, array_slice($_SERVER['argv'] ?? [], 2));
        foreach ($rawArgs as $k => $v) {
            $candidate = is_string($v) && $v !== '' ? $v : (is_string($k) ? $k : '');
            $candidate = ltrim($candidate, '-');

            if (str_starts_with($candidate, 'days=')) {
                $rawDays = substr($candidate, 5);
            } elseif (str_starts_with($candidate, 'batch=')) {
                $rawBatch = substr($candidate, 6);
            } elseif ($candidate === 'dry-run') {
                $dryRun = true;
            } elseif ($candidate === 'loop') {
                $loop = true;
            } elseif (str_starts_with($candidate, 'sleep=')) {
                $rawSleep = substr($candidate, 6);
            } elseif (str_starts_with($candidate, 'iterations=')) {
                $rawMaxIter = substr($candidate, 11);
                $hasMaxIter = true;
            }
        }

        $days         = max(1, (int) ($rawDays ?? 28)); // 28 días por defecto
        $batch        = max(50, (int) ($rawBatch ?? 500));
        $sleepSeconds = max(1, (int) ($rawSleep ?? 43200)); // 12 horas en loop
        $maxIter      = $hasMaxIter ? max(1, (int) $rawMaxIter) : ($loop ? 0 : 1);

        // Control de concurrencia: Lock exclusivo vía MySQL GET_LOCK nativo
        $db       = \Config\Database::connect();
        $lockName = 'lock_ntf_purge';
        $res      = $db->query("SELECT GET_LOCK(?, 0) AS acquired", [$lockName])->getRow();

        if ((int) ($res->acquired ?? 0) !== 1) {
            CLI::write("⚠️  Ya hay una tarea de purga en ejecución; saliendo.", 'yellow');
            return;
        }

        CLI::write("─────────────────────────────────────────────────────────────", 'cyan');
        CLI::write("🧹 Purga de Notificaciones [sys_notification]", 'yellow');
        CLI::write("• Retención:   {$days} días", 'white');
        CLI::write("• Lote:        {$batch} registros por query", 'white');
        CLI::write("• Modo:        " . ($dryRun ? "SIMULACIÓN (Dry-run)" : ($loop ? "DEMONIO / BUCLE (sleep: {$sleepSeconds}s)" : "PUNTUAL (1 iteración)")), $dryRun ? 'yellow' : 'white');
        CLI::write("• Lock:        MySQL GET_LOCK ({$lockName})", 'white');
        CLI::write("─────────────────────────────────────────────────────────────", 'cyan');

        $iteration = 0;

        try {
            while (true) {
                $iteration++;
                $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

                // Notificaciones elegibles para purga: Enviadas ('1'), Leídas ('3') o DLQ ('4')
                $purgeStatuses = [
                    NotificationModel::STATUS_SENT,
                    NotificationModel::STATUS_READ,
                    NotificationModel::STATUS_DLQ,
                ];

                if ($dryRun) {
                    $count = $db->table('sys_notification')
                        ->whereIn('status', $purgeStatuses)
                        ->where('created_at <', $cutoff)
                        ->countAllResults();

                    CLI::write("[PURGE_DRY_RUN] Se encontraron {$count} notificaciones elegibles para purga anteriores a {$cutoff}.", 'yellow');
                    $this->writeLog("[PURGE_DRY_RUN] Evaluadas {$count} filas para corte {$cutoff}.");
                    break;
                }

                $totalDeleted = 0;
                while (true) {
                    $ids = $db->table('sys_notification')
                        ->select('id')
                        ->whereIn('status', $purgeStatuses)
                        ->where('created_at <', $cutoff)
                        ->limit($batch)
                        ->get()
                        ->getResultArray();

                    if (empty($ids)) {
                        break;
                    }

                    $idList = array_column($ids, 'id');
                    $db->table('sys_notification')->whereIn('id', $idList)->delete();
                    $totalDeleted += count($idList);
                }

                if ($totalDeleted === 0) {
                    if (!$loop || $iteration === 1) {
                        CLI::write("No hay notificaciones antiguas para purgar anteriores a {$cutoff}.", 'green');
                    }
                } else {
                    $summary = "[PURGE_COMPLETED] Purgadas {$totalDeleted} notificaciones anteriores a {$cutoff}.";
                    $this->writeLog($summary);
                    CLI::write("✅ {$summary}", 'green');
                }

                if ($maxIter > 0 && $iteration >= $maxIter) {
                    break;
                }

                sleep($sleepSeconds);
            }
        } finally {
            $db->query("SELECT RELEASE_LOCK(?)", [$lockName]);
        }

        CLI::write("✅ Purga finalizada.", 'cyan');
    }

    private function writeLog(string $message): void
    {
        $dir = WRITEPATH . 'logs/notifications/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $logFile   = $dir . 'purge-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
    }
}