<?php

namespace App\Libraries\Comms\Exceptions;

class NoActiveChannelException extends CommsException
{
    public function __construct(int $userId, string $message = '')
    {
        $detail = $message !== '' ? " ({$message})" : '';
        parent::__construct("No hay canales de notificación activos o configurados para el usuario [{$userId}]{$detail}.");
    }
}
