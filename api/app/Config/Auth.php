<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Auth extends BaseConfig
{
    public int $failedAttemptLimit = 3;
    public int $failedAttemptLapse = 86400;
    public bool $userCaseSensitive = true;
}
