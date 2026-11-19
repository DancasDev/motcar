<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Gac extends BaseConfig
{
    public string $cacheKey = 'gac_access';
    public int $cacheDuration = 600;
}
