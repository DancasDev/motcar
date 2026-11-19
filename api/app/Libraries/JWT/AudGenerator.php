<?php

namespace App\Libraries\JWT;

/**
 * Genera el valor "aud" (audiencia) para los tokens JWT.
 * Sirve para vincular el token al dispositivo/navegador que lo solicitó.
 *
 * - userAud:  hashea solo el User-Agent → mismo navegador, distinta IP = válido
 * - clientAud: hashea IP + User-Agent → más restrictivo (para API clients)
 */
class AudGenerator {
    public static function buildHash(array $items): string {
        return !empty($items) ? hash('crc32', implode('@', $items)) : '';
    }

    public static function userAud(): string {
        return self::buildHash([\Config\Services::request()->getUserAgent()->getAgentString()]);
    }

    public static function clientAud(): string {
        $request = \Config\Services::request();
        return self::buildHash([$request->getIPAddress(), $request->getUserAgent()->getAgentString()]);
    }
}
