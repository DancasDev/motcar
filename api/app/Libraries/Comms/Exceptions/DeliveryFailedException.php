<?php

namespace App\Libraries\Comms\Exceptions;

use Throwable;

class DeliveryFailedException extends CommsException
{
    private string $channel;

    public function __construct(string $channel, string $message = '', int $code = 502, ?Throwable $previous = null)
    {
        $this->channel = $channel;
        $msg = "Error en la entrega vía canal [{$channel}]" . (!empty($message) ? ": {$message}" : '.');
        parent::__construct($msg, $code, $previous);
    }

    public function getChannel(): string
    {
        return $this->channel;
    }
}
