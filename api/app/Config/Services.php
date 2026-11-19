<?php

namespace Config;

use CodeIgniter\Config\BaseService;

class Services extends BaseService {
    public static function authToken($getShared = true) {
        if ($getShared) {
            return static::getSharedInstance('authToken');
        }

        $config = config('Jwt');
        if (empty($config->authSecretKey) || empty($config->authRevocationKey)) {
            log_message('critical', 'Las configuraciones "jwt.authSecretKey" y "jwt.authRevocationKey" no están definidas.');
            throw new \Exception('No se han definido las configuraciones "jwt.authSecretKey" y "jwt.authRevocationKey"');
        }

        $class = new \App\Libraries\JWT\Token();
        $class->setSecretKey($config->authSecretKey);
        $class->setRevocationKey($config->authRevocationKey);

        return $class;
    }

    public static function context(bool $getShared = true): \App\Libraries\Core\RequestContext {
        if ($getShared) {
            return static::getSharedInstance('context');
        }

        return new \App\Libraries\Core\RequestContext();
    }
    
    public static function authAccess($getShared = true) {
        if ($getShared) {
            return static::getSharedInstance('authAccess');
        }

        $config = config('Gac');
        if (empty($config->cacheKey) || empty($config->cacheDuration)) {
            log_message('critical', 'Las configuraciones "gac.cacheKey" y "gac.cacheDuration" no están definidas.');
            throw new \Exception('No se han definido las configuraciones "gac.cacheKey" y "gac.cacheDuration"');
        }

        return new \App\Libraries\GAC\Access($config->cacheKey, $config->cacheDuration);
    }

    public static function otpManager($getShared = true) {
        if ($getShared) {
            return static::getSharedInstance('otpManager');
        }

        return new \App\Libraries\Otp\OtpManager();
    }

    public static function storage(bool $isPrivate = false, bool $getShared = true): \League\Flysystem\Filesystem {
        if ($getShared) {
            $key = $isPrivate ? 'storage_private' : 'storage_public';
            if (!isset(static::$instances[$key])) {
                static::$instances[$key] = \App\Libraries\Storage\StorageFactory::create($isPrivate);
            }

            return static::$instances[$key];
        }

        return \App\Libraries\Storage\StorageFactory::create($isPrivate);
    }

    public static function actionToken(bool $getShared = false): \App\Libraries\JWT\Token {
        if ($getShared) {
            return static::getSharedInstance('actionToken');
        }

        $config = config('Jwt');
        if (empty($config->actionSecretKey) || empty($config->actionRevocationKey)) {
            log_message('critical', 'Las configuraciones "jwt.actionSecretKey" y "jwt.actionRevocationKey" no están definidas.');
            throw new \Exception('No se han definido las configuraciones "jwt.actionSecretKey" y "jwt.actionRevocationKey"');
        }

        $token = new \App\Libraries\JWT\Token();
        $token->setSecretKey($config->actionSecretKey);
        $token->setRevocationKey($config->actionRevocationKey);

        return $token;
    }

    public static function messaging(bool $getShared = true): \App\Libraries\Comms\Direct\DirectMessenger {
        if ($getShared) {
            return static::getSharedInstance('messaging');
        }
        return new \App\Libraries\Comms\Direct\DirectMessenger();
    }

    public static function notification(bool $getShared = true): \App\Libraries\Comms\Outbox\NotificationService {
        if ($getShared) {
            return static::getSharedInstance('notification');
        }
        return new \App\Libraries\Comms\Outbox\NotificationService();
    }
}

