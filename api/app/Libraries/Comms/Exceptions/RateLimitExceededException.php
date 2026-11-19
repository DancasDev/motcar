<?php

namespace App\Libraries\Comms\Exceptions;

class RateLimitExceededException extends CommsException
{
    private string $channel;
    private int $retryAfterSeconds;

    public function __construct(string $channel, int $retryAfterSeconds)
    {
        $this->channel = $channel;
        $this->retryAfterSeconds = $retryAfterSeconds;
        parent::__construct("Límite de frecuencia excedido para el canal [{$channel}]. Reintente en {$retryAfterSeconds} segundos.", 429);
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
