<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\Comms\Outbox\NotificationDispatcher;
use Config\Comms;

/**
 * Comando para despachar la cola de notificaciones externas (Email, Push).
 * 
 * Modos de ejecución:
 *  - Específico / Inmediato:  --ids=1,2,3 (bypasea el lock de slots; lock atómico por fila en MySQL)
 *  - Demonio en segundo plano: --loop [--sleep=3] (adquiere slot de worker concurrente vía MySQL GET_LOCK)
 *  - Puntual / Cron OS:       (1 iteración sobre la cola respetando slots de workers)
 */
class NotificationsDispatch extends BaseCommand {
    protected $group       = 'Notifications';
    protected $name        = 'notifications:dispatch';
    protected $description = 'Despacha la cola de notificaciones externas (Email, Push).';
    protected $usage       = 'notifications:dispatch [--channels=email,push] [--ids=1,2,3] [--loop] [--sleep=3] [--iterations=N]';

    public function run(array $params) {
        $ids          = null;
        $channels     = null;
        $rawIds       = CLI::getOption('ids') ?? ($params['ids'] ?? null);
        $rawChannels  = CLI::getOption('channels') ?? ($params['channels'] ?? null);
        $loop         = CLI::getOption('loop') !== null || in_array('loop', $params, true) || in_array('--loop', $params, true) || !empty($params['loop']);
        $rawSleep     = CLI::getOption('sleep') ?? ($params['sleep'] ?? null);
        $hasMaxIter   = CLI::getOption('iterations') !== null || isset($params['iterations']);
        $rawMaxIter   = CLI::getOption('iterations') ?? ($params['iterations'] ?? null);

        // 1. Procesar argumentos CLI (compatibilidad con argv directo y llamadas programáticas)
        $rawArgs = array_merge($params, array_slice($_SERVER['argv'] ?? [], 2));
        foreach ($rawArgs as $k => $v) {
            $candidate = is_string($v) && $v !== '' ? $v : (is_string($k) ? $k : '');
            $candidate = ltrim($candidate, '-');

            if (str_starts_with($candidate, 'ids=')) {
                $rawIds = substr($candidate, 4);
            } elseif (str_starts_with($candidate, 'channels=')) {
                $rawChannels = substr($candidate, 9);
            } elseif ($candidate === 'loop') {
                $loop = true;
            } elseif (str_starts_with($candidate, 'sleep=')) {
                $rawSleep = substr($candidate, 6);
            } elseif (str_starts_with($candidate, 'iterations=')) {
                $rawMaxIter = substr($candidate, 11);
                $hasMaxIter = true;
            }
        }

        $maxIter      = $hasMaxIter ? max(1, (int) $rawMaxIter) : ($loop ? 0 : 1);
        $sleepSeconds = max(1, (int) ($rawSleep ?? 3));

        if ($rawIds !== null && $rawIds !== '') {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $rawIds))));
        }
        if ($rawChannels !== null && $rawChannels !== '') {
            $channels = array_values(array_filter(array_map('trim', explode(',', (string) $rawChannels))));
        }

        // 2. Normalizar y ordenar canales (consistente con rate limiting: email, push, etc.)
        /** @var Comms $comms */
        $comms = config('Comms');
        if ($channels === null || empty($channels)) {
            $channels = $comms->notificationChannels ?? ['email', 'push'];
        }

        // Filtrar solo canales externos soportados por el despachador Outbox
        $normalizedChannels = array_values(array_filter(
            array_map([Comms::class, 'normalizeChannelName'], $channels),
            static fn($c) => in_array($c, ['email', 'push'], true)
        ));
        sort($normalizedChannels); // Orden alfabético para clave determinista

        $channelsKey = implode('_', $normalizedChannels);
        $channelsStr = implode(', ', $normalizedChannels);
        $isAdHoc     = $ids !== null && $ids !== [];

        // 3. Control de concurrencia y slots de workers (MySQL GET_LOCK nativo)
        $db          = \Config\Database::connect();
        $maxWorkers  = $comms->dispatchMaxWorkers;
        $claimedLock = null;
        $slotNumber  = 0;

        if (!$isAdHoc) {
            for ($slot = 1; $slot <= $maxWorkers; $slot++) {
                $lockName = "lock_ntf_{$channelsKey}_{$slot}";
                $res = $db->query("SELECT GET_LOCK(?, 0) AS acquired", [$lockName])->getRow();
                if ((int) ($res->acquired ?? 0) === 1) {
                    $claimedLock = $lockName;
                    $slotNumber  = $slot;
                    break;
                }
            }

            if ($claimedLock === null) {
                CLI::write("⚠️  Límite de workers alcanzado ({$maxWorkers}) para el grupo [{$channelsKey}]; saliendo.", 'yellow');
                return;
            }
        }

        // 4. Banner informativo de ejecución
        $modeDesc = $isAdHoc 
            ? "INMEDIATO (IDs: " . implode(',', $ids) . ")" 
            : ($loop ? "DEMONIO / BUCLE (sleep: {$sleepSeconds}s)" : "PUNTUAL (1 iteración)");

        CLI::write("─────────────────────────────────────────────────────────────", 'cyan');
        CLI::write("🚀 Despachador de Notificaciones [Canales: {$channelsStr}]", 'green');
        CLI::write("• Modo:        {$modeDesc}", 'white');
        if ($claimedLock !== null) {
            CLI::write("• Slot Lock:   {$claimedLock} (Worker {$slotNumber}/{$maxWorkers} vía MySQL)", 'white');
        } else {
            CLI::write("• Concurrencia: MySQL Row-Lock atómico (Bypass de slot)", 'white');
        }
        CLI::write("─────────────────────────────────────────────────────────────", 'cyan');

        $iteration = 0;
        $dispatcher = new NotificationDispatcher();

        try {
            while (true) {
                $iteration++;
                $startTime = microtime(true);

                // Ejecutar despacho del lote
                $result = $dispatcher->dispatch($ids, null, $normalizedChannels);
                $duration = round(microtime(true) - $startTime, 4);

                $logLine = sprintf(
                    "[DISPATCH] [Ch:%s] Iter: %d | Proc: %d | Sent: %d | Defer: %d | Fail: %d | DLQ: %d | %.4fs",
                    $channelsStr,
                    $iteration,
                    $result['processed'],
                    $result['sent'],
                    $result['deferred'],
                    $result['failed'],
                    $result['dlq'],
                    $duration
                );

                $this->writeLog($logLine);

                if ($result['processed'] > 0 || $iteration === 1 || !$loop) {
                    CLI::write($logLine, $result['sent'] > 0 ? 'green' : 'white');
                }

                // Condición de salida para ejecuciones con límite de iteraciones o modo puntual
                if ($maxIter > 0 && $iteration >= $maxIter) {
                    break;
                }

                // Drenaje adaptativo: dormir solo si la cola está vacía o si todos fueron diferidos por rate limit
                if ($result['processed'] === 0 || ($result['sent'] === 0 && $result['deferred'] > 0)) {
                    sleep($sleepSeconds);
                }
            }
        } finally {
            if ($claimedLock !== null) {
                $db->query("SELECT RELEASE_LOCK(?)", [$claimedLock]);
            }
        }

        CLI::write("✅ Despacho finalizado.", 'cyan');
    }

    private function writeLog(string $message): void {
        $dir = WRITEPATH . 'logs/notifications/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $logFile   = $dir . 'dispatch-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
    }
}