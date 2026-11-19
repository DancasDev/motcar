<?php

namespace App\Libraries\Comms\RateLimit;

use App\Libraries\Comms\Exceptions\RateLimitExceededException;
use CodeIgniter\Cache\CacheInterface;
use Config\Comms;

class CommsRateLimiter
{
    private CacheInterface $cache;

    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache ?? service('cache');
    }

    /**
     * Verifica e incrementa el contador atómico del canal.
     *
     * @throws RateLimitExceededException Si el canal supera su cuota en la ventana activa.
     */
    public function hit(string $channel): void
    {
        /** @var Comms $comms */
        $comms = config('Comms');
        $channelName = Comms::normalizeChannelName($channel);

        if ($channelName === 'inapp') {
            return; // In-App no tiene rate limit externo
        }

        $window = $comms->windowFor($channelName);
        $max    = $comms->maxFor($channelName);
        $prefix = $comms->rateLimitCachePrefix ?: 'rl_msg_';
        $key    = $prefix . $channelName;

        $rawCurrent = $this->cache->get($key);
        if ($rawCurrent === null || $rawCurrent === false) {
            $current = 1;
            $this->cache->save($key, 1, $window);
        } else {
            $incr = $this->cache->increment($key, 1);
            $current = is_int($incr) ? $incr : ((int) $rawCurrent + 1);
            if (!is_int($incr)) {
                $this->cache->save($key, $current, $window);
            }
        }

        if ($current > $max) {
            $remaining = $window;
            if (method_exists($this->cache, 'ttl')) {
                $ttl = $this->cache->ttl($key);
                $remaining = is_numeric($ttl) && $ttl > 0 ? (int) $ttl : $window;
            } elseif (method_exists($this->cache, 'getMetaData')) {
                $meta = $this->cache->getMetaData($key);
                if (isset($meta['expire']) && is_numeric($meta['expire'])) {
                    $remaining = max(1, (int) $meta['expire'] - time());
                }
            }
            throw new RateLimitExceededException($channelName, $remaining);
        }
    }

    /**
     * Retorna true si el canal está actualmente en estado de limitación.
     */
    public function isThrottled(string $channel): bool
    {
        try {
            $this->hit($channel);
            return false;
        } catch (RateLimitExceededException) {
            return true;
        }
    }
}
