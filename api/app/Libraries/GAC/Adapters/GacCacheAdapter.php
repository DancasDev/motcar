<?php

namespace App\Libraries\GAC\Adapters;

use DancasDev\GAC\Drivers\Cache\CacheInterface;

class GacCacheAdapter implements CacheInterface {
    private $cache = null;

    public function __construct() {
        $this->cache = service('cache');
    }

    public function get(string $key): mixed {
        return $this->cache->get($key);
    }

    public function save(string $key, mixed $data, ?int $ttl = 60): bool {
        if (empty($ttl)) {
            return $this->cache->save($key, $data);
        }

        return $this->cache->save($key, $data, $ttl);
    }

    public function delete(string $key): bool {
        return $this->cache->delete($key);
    }

    public function deleteMatching(string $pattern): int {
        return $this->cache->deleteMatching($pattern);
    }

    public function clean(): bool {
        return $this->cache->clean();
    }
}
