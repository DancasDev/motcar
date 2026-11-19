<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración unificada del ecosistema de comunicaciones (Messaging y Notificaciones).
 */
class Comms extends BaseConfig
{
    /** @var list<string> */
    public array $messagingChannels = ['email', 'push', 'telegram'];

    /** @var list<string> */
    public array $notificationChannels = ['inapp', 'email', 'push'];

    /** @var array<string, int> */
    public array $rateLimitWindows = [
        'email'    => 3600,
        'push'     => 60,
        'telegram' => 1,
    ];

    /** @var array<string, int> */
    public array $rateLimitMax = [
        'email'    => 100,
        'push'     => 500,
        'telegram' => 30,
    ];

    public string $firebaseProjectId = '';
    public string $firebaseCredentialsFile = 'writable/keys/firebase-service-account.json';
    public string $firebaseCredentialsJson = '';

    public string $pushDefaultIcon = 'https://cdn-icons-png.flaticon.com/512/1827/1827392.png';
    public string $pushDefaultBadge = 'https://cdn-icons-png.flaticon.com/512/1827/1827392.png';
    public string $pushDefaultColor = '#06b6d4';

    public int $dispatchBatch = 100;
    public int $dispatchMaxWorkers = 1;
    public int $baseBackoffSeconds = 60;
    public int $maxBackoffSeconds = 3600;
    public int $maxRetries = 3;

    public string $rateLimitCachePrefix = 'rl_msg_';

    public function __construct()
    {
        parent::__construct();

        $envRateLimitPrefix = env('comms.rateLimitCachePrefix') ?? env('comms.rate_limit_cache_prefix');
        if (is_string($envRateLimitPrefix) && $envRateLimitPrefix !== '') {
            $this->rateLimitCachePrefix = $envRateLimitPrefix;
        }

        $envMessaging = env('comms.messagingChannels');
        if (is_string($envMessaging)) {
            $this->messagingChannels = $this->parseChannelList($envMessaging);
        }

        $envNotification = env('comms.notificationChannels');
        if (is_string($envNotification)) {
            $this->notificationChannels = $this->parseChannelList($envNotification);
        }

        foreach (['email', 'push', 'telegram'] as $ch) {
            $uc = ucfirst($ch);
            $this->rateLimitWindows[$ch] = (int) env("comms.rate{$uc}Window", $this->rateLimitWindows[$ch]);
            $this->rateLimitMax[$ch]     = (int) env("comms.rate{$uc}Max",    $this->rateLimitMax[$ch]);
        }
    }

    public function isMessagingChannelEnabled(string $channel): bool
    {
        return in_array(self::normalizeChannelName($channel), $this->messagingChannels, true);
    }

    public function isNotificationChannelEnabled(string $channelNameOrId): bool
    {
        return in_array(self::normalizeChannelName($channelNameOrId), $this->notificationChannels, true);
    }

    public static function normalizeChannelName(string $channel): string
    {
        return match (strtolower(trim($channel))) {
            '0', 'inapp'                  => 'inapp',
            '1', 'email'                  => 'email',
            '2', 'push', 'webpush', 'fcm' => 'push',
            '3', 'telegram'               => 'telegram',
            default                       => strtolower(trim($channel)),
        };
    }

    public static function normalizeChannelCode(string $channel): string
    {
        return match (self::normalizeChannelName($channel)) {
            'email'    => '1',
            'push'     => '2',
            'telegram' => '3',
            default    => '0',
        };
    }

    public function windowFor(string $channel): int
    {
        return $this->rateLimitWindows[self::normalizeChannelName($channel)] ?? 60;
    }

    public function maxFor(string $channel): int
    {
        return $this->rateLimitMax[self::normalizeChannelName($channel)] ?? 50;
    }

    private function parseChannelList(string $raw): array
    {
        return array_values(array_filter(
            array_map([self::class, 'normalizeChannelName'], explode(',', $raw))
        ));
    }
}
