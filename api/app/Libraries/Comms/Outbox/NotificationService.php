<?php

namespace App\Libraries\Comms\Outbox;

/**
 * Servicio de despacho en segundo plano para notificaciones del sistema.
 * Responsabilidad única: Encolar la ejecución en segundo plano del comando spark notifications:send
 * sin bloquear la petición HTTP del usuario.
 */
class NotificationService
{
    /**
     * Dispara el registro y eventual despacho de la notificación en un proceso CLI en segundo plano.
     *
     * @param int|int[]            $userIds  ID o IDs de usuarios destinatarios
     * @param string               $typeCode Código del tipo de notificación (sys_notification_type.code)
     * @param array<string, mixed> $data     Variables dinámicas para interpolar
     * @param bool                 $dispatch Si es true, el proceso en segundo plano intentará despachar de inmediato
     */
    public function send(
        int|array $userIds,
        string $typeCode,
        array $data = [],
        bool $dispatch = false
    ): void {
        $targetIds = array_values(array_unique(array_filter(array_map('intval', (array) $userIds))));
        if (empty($targetIds)) {
            return;
        }

        $idList = implode(',', $targetIds);
        $sparkPath = escapeshellarg(ROOTPATH . 'spark');
        $phpBinary = escapeshellarg(PHP_BINARY ?: 'php');
        $safeType  = escapeshellarg($typeCode);

        $command = "{$phpBinary} {$sparkPath} notifications:send --type={$safeType} --ids={$idList}";

        if (!empty($data)) {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                $command .= ' --data=' . escapeshellarg($json);
            }
        }

        if ($dispatch) {
            $command .= ' --dispatch';
        }

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B \"\" {$command} > NUL 2>&1", 'r'));
        } else {
            exec("{$command} > /dev/null 2>&1 &");
        }
    }
}

