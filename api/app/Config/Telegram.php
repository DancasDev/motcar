<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Telegram extends BaseConfig
{
    public string $botToken = '';
    public string $webhookSecret = '';
    public string $botUsername = '';
    public int $bindingTtlSeconds = 60;
    public int $pendingPayloadTtlSeconds = 120;
    public string $bindingCachePrefix = 'tmp_tg_sub_';

    public function __construct()
    {
        parent::__construct();

        $this->botToken = (string) (env('telegram.botToken') ?? env('telegram.bot_token') ?? $this->botToken);
        $this->webhookSecret = (string) (env('telegram.webhookSecret') ?? env('telegram.webhook_secret') ?? $this->webhookSecret);
        $this->botUsername = (string) (env('telegram.botUsername') ?? env('telegram.bot_username') ?? $this->botUsername);
        $this->bindingCachePrefix = (string) (env('telegram.bindingCachePrefix') ?? env('telegram.binding_cache_prefix') ?? $this->bindingCachePrefix);
    }
}
