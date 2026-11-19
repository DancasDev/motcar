<?php

namespace App\Libraries\GAC;

use DancasDev\GAC\GAC;
use DancasDev\GAC\Drivers\Database\MysqliConnection;
use App\Libraries\GAC\Adapters\GacCacheAdapter;

final class Access extends GAC {
    private string $cacheKeyValue = 'gac';
    private int $cacheTtlValue = 600;

    public function __construct(?string $cacheKeyValue = null, ?int $cacheTtlValue = null) {
        $cacheKeyValue = $cacheKeyValue ?? $this->cacheKeyValue;
        $cacheTtlValue = $cacheTtlValue ?? $this->cacheTtlValue;

        $db = db_connect();
        $db->initialize();
        $connection = new MysqliConnection($db->mysqli);
        $cache = new GacCacheAdapter();

        parent::__construct($connection, $cache);

        $this->setCacheKey($cacheKeyValue);
        $this->setCacheTtl($cacheTtlValue);
    }
}
