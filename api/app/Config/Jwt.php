<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Jwt extends BaseConfig
{
    // Sesión (Auth JWT)
    public string $authSecretKey = '';
    public string $authRevocationKey = 'jwt_auth_rev';
    public int $userTokenDuration = 2700;
    public int $userTokenUpdateInterval = 600;
    public int $clientTokenDuration = 10800;
    public int $clientTokenUpdateInterval = 600;

    // Acciones (Action JWT)
    public string $actionSecretKey = '';
    public string $actionRevocationKey = 'jwt_act_rev';

    // TTLs de acciones (jwt.action*)
    public int $actionDefaultTtl = 3600;
    public int $actionUserInvitationTtl = 259200;
    public int $actionStorageDownloadTtl = 3600;
    public int $actionTelegramBindTtl = 300;

    /**
     * Retorna el TTL configurado para la acción solicitada.
     * Si no tiene uno específico, devuelve el valor por defecto seguro.
     */
    public function getActionTtl(string $action): int
    {
        return match ($action) {
            'user_invitation'  => $this->actionUserInvitationTtl,
            'storage_download' => $this->actionStorageDownloadTtl,
            'telegram_bind'    => $this->actionTelegramBindTtl,
            default            => $this->actionDefaultTtl,
        };
    }
}
