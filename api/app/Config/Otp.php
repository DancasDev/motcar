<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración del subsistema de Códigos OTP y Canales de Seguridad.
 */
class Otp extends BaseConfig
{
    /**
     * Canales autorizados para emitir y entregar códigos OTP.
     * Valores posibles: email, telegram
     *
     * @var list<string>
     */
    public array $allowedChannels = ['email', 'telegram'];

    /**
     * Tiempo de expiración por defecto en minutos si no está en la base de datos.
     */
    public int $defaultExpirationMinute = 10;

    /**
     * Margen de reenvío por defecto en minutos.
     */
    public int $defaultResendMarginMinute = 1;

    /**
     * Intentos máximos de verificación permitidos.
     */
    public int $maxAttempts = 5;

    public function __construct()
    {
        parent::__construct();

        $envAllowed = env('otp.allowedChannels');
        if (is_string($envAllowed)) {
            $this->allowedChannels = array_values(array_filter(
                array_map('trim', explode(',', strtolower($envAllowed)))
            ));
        }
    }

    /**
     * Valida si un canal está autorizado para entregar códigos OTP.
     */
    public function isChannelAllowed(string $channel): bool
    {
        $normalized = match (strtolower(trim($channel))) {
            '0', 'email'    => 'email',
            '1', 'telegram' => 'telegram',
            default         => strtolower(trim($channel)),
        };

        return in_array($normalized, $this->allowedChannels, true);
    }
}
