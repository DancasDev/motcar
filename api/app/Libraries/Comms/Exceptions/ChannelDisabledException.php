<?php

namespace App\Libraries\Comms\Exceptions;

class ChannelDisabledException extends CommsException
{
    public function __construct(string $channel)
    {
        parent::__construct("El canal de comunicación [{$channel}] está deshabilitado en la configuración.");
    }
}
