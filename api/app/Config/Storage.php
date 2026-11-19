<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Storage extends BaseConfig {
    public string $driver = 'local';

    // Local
    public string $localPublicRoot  = FCPATH . 'uploads/';
    public string $localPrivateRoot = WRITEPATH . 'uploads/';
    public string $sendfileHeader   = '';

    // S3
    public string $s3Key    = '';
    public string $s3Secret = '';
    public string $s3Region = '';
    public string $s3Bucket = '';
    public string $s3Url    = '';
    public string $s3Prefix = 'uploads/';

    // Optimización
    public string $ghostscriptBin = 'gs';
}
