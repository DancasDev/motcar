<?php

namespace App\Libraries\Comms\Outbox;

class NotificationResult
{
    public int $insertedCount = 0;         // Filas insertadas en sys_notification
    public array $channelsQueued = [];     // ['email', 'push'] — canales externos encolados
    public array $externalIds = [];        // IDs generados para canales externos
}
