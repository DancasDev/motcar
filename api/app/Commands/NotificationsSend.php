<?php

namespace App\Commands;

use App\Libraries\Comms\Outbox\NotificationDispatcher;
use App\Libraries\Comms\Outbox\NotificationSender;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Comando CLI para procesar el registro de notificaciones en segundo plano
 * y opcionalmente despachar de forma síncrona en el mismo proceso.
 */
class NotificationsSend extends BaseCommand
{
    protected $group       = 'Notifications';
    protected $name        = 'notifications:send';
    protected $description = 'Registra notificaciones para uno o más usuarios y opcionalmente despacha.';
    protected $usage       = 'notifications:send --type=<code> --ids=<id1,id2> [--data=<json>] [--dispatch]';

    public function run(array $params)
    {
        $typeCode = CLI::getOption('type') ?? ($params['type'] ?? null);
        $rawIds   = CLI::getOption('ids') ?? ($params['ids'] ?? null);
        $rawData  = CLI::getOption('data') ?? ($params['data'] ?? null);
        $dispatch = CLI::getOption('dispatch') !== null || in_array('dispatch', $params, true) || in_array('--dispatch', $params, true) || !empty($params['dispatch']);

        // Procesar argumentos CLI (compatibilidad con argv directo y llamadas programáticas)
        $rawArgs = array_merge($params, array_slice($_SERVER['argv'] ?? [], 2));
        foreach ($rawArgs as $k => $v) {
            $candidate = is_string($v) && $v !== '' ? $v : (is_string($k) ? $k : '');
            $candidate = ltrim($candidate, '-');

            if (str_starts_with($candidate, 'type=')) {
                $typeCode = substr($candidate, 5);
            } elseif (str_starts_with($candidate, 'ids=')) {
                $rawIds = substr($candidate, 4);
            } elseif (str_starts_with($candidate, 'data=')) {
                $rawData = substr($candidate, 5);
            } elseif ($candidate === 'dispatch') {
                $dispatch = true;
            }
        }

        if (empty($typeCode)) {
            CLI::error('El parámetro --type=<código> es requerido.');
            return;
        }

        if (empty($rawIds)) {
            CLI::error('El parámetro --ids=<id1,id2,...> es requerido.');
            return;
        }

        $userIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $rawIds)))));
        if (empty($userIds)) {
            CLI::error('No se especificaron IDs válidos en --ids.');
            return;
        }

        $data = [];
        if (!empty($rawData)) {
            $decoded = json_decode((string) $rawData, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $sender = new NotificationSender();

        try {
            $result = $sender->register($userIds, $typeCode, $data);

            CLI::write("✅ Notificaciones registradas para tipo [{$typeCode}]: {$result->insertedCount} filas.", 'green');

            // Despacho síncrono inmediato si se indicó el flag --dispatch
            if ($dispatch && !empty($result->externalIds)) {
                CLI::write("🚀 Despachando inmediatamente " . count($result->externalIds) . " notificación(es) externa(s)...", 'cyan');
                $dispatcher = new NotificationDispatcher();
                $dispatchStats = $dispatcher->dispatch($result->externalIds);

                CLI::write(
                    sprintf(
                        "• Despacho: Proc: %d | Sent: %d | Defer: %d | Fail: %d | DLQ: %d",
                        $dispatchStats['processed'],
                        $dispatchStats['sent'],
                        $dispatchStats['deferred'],
                        $dispatchStats['failed'],
                        $dispatchStats['dlq']
                    ),
                    $dispatchStats['sent'] > 0 ? 'green' : 'yellow'
                );
            }
        } catch (Throwable $e) {
            log_message('error', "[NotificationsSend] Error registrando notificación [{$typeCode}]: " . $e->getMessage());
            CLI::error("Error registrando notificaciones: " . $e->getMessage());
        }
    }
}
