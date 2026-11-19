<?php

namespace App\Libraries\Comms\Direct;

use App\Libraries\Comms\Drivers\EmailDriver;
use App\Libraries\Comms\Drivers\FcmPushDriver;
use App\Libraries\Comms\Drivers\TelegramDriver;
use App\Libraries\Comms\Exceptions\ChannelDisabledException;
use App\Libraries\Comms\Exceptions\DeliveryFailedException;
use App\Libraries\Comms\Exceptions\RateLimitExceededException;
use App\Libraries\Comms\RateLimit\CommsRateLimiter;
use Config\Comms;

/**
 * Servicio de mensajería directa, inmediata y transaccional (OTPs, tokens, alertas síncronas).
 */
class DirectMessenger
{
    private CommsRateLimiter $rateLimiter;
    private array $drivers = [];

    public function __construct(?CommsRateLimiter $rateLimiter = null)
    {
        $this->rateLimiter = $rateLimiter ?? new CommsRateLimiter();
    }

    public static function channelAvailable(string $channel): bool
    {
        /** @var Comms $comms */
        $comms = config('Comms');
        return $comms->isMessagingChannelEnabled($channel);
    }

    /**
     * Envía un mensaje transaccional directo e inmediato.
     *
     * @param string       $channel Canal ('email', 'push', 'telegram')
     * @param string|array $to      Destinatario (email, push token, chat ID o array de ellos)
     * @param string       $subject Asunto o título
     * @param string|array $body    Cuerpo formateado
     * @param array        $options Opciones adicionales
     *
     * @throws ChannelDisabledException
     * @throws RateLimitExceededException
     * @throws DeliveryFailedException
     */
    public function send(
        string $channel,
        string|array $to,
        string $subject,
        string|array $body,
        array $options = []
    ): void {
        /** @var Comms $comms */
        $comms = config('Comms');

        if (!$comms->isMessagingChannelEnabled($channel)) {
            throw new ChannelDisabledException($channel);
        }

        $channelName = Comms::normalizeChannelName($channel);
        $this->rateLimiter->hit($channelName);

        $driver = $this->getDriver($channelName);
        $driver->send($to, $subject, $body, $options);
    }

    private function getDriver(string $channelName)
    {
        if (!isset($this->drivers[$channelName])) {
            $this->drivers[$channelName] = match ($channelName) {
                'email'    => new EmailDriver(),
                'push'     => new FcmPushDriver(),
                'telegram' => new TelegramDriver(),
                default    => throw new ChannelDisabledException($channelName),
            };
        }

        return $this->drivers[$channelName];
    }
}
