<?php

namespace App\Libraries\JWT;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Validation\Constraint\SignedWith;

/**
 * Manejo de tokens JWT con revocación en 2 ámbitos (Específico, Global).
 */
final class Token {
    /** @var Configuration Configuración reutilizable de lcobucci */
    private Configuration $config;

    /** @var array Claims del token actual (iss, exp, sub, etc.) */
    private array $payload = [];

    /** @var int Duración del token en segundos (por defecto 45 min) */
    private int $expirationTime = 2700;

    /** @var string Clave secreta para firmar/verificar HMAC-SHA256 */
    private string $secretKey = '';

    /** @var string Prefijo para las claves de caché de revocación */
    private string $revocationKey = 'jwt_auth_rev';

    // ─── Configuración ────────────────────────────────────────

    public function setExpirationTime(int $value): self {
        if ($value < 1) {
            throw new \InvalidArgumentException('The expiration time must be greater than 0');
        }
        $this->expirationTime = $value;
        return $this;
    }

    public function setSecretKey(string $value): self {
        $this->secretKey = $value;
        return $this;
    }

    public function setRevocationKey(string $value): self {
        $this->revocationKey = $value;
        return $this;
    }

    // ─── Payload ──────────────────────────────────────────────

    /**
     * Agrega uno o más claims al payload del token.
     * Puede recibir un array asociativo o un par clave/valor.
     */
    public function setPayloadValue(string|array $field, mixed $value = null): self {
        $list = is_array($field) ? $field : [$field => $value];
        foreach ($list as $f => $v) {
            $this->payload[$f] = $v;
        }
        return $this;
    }

    /** Devuelve todos los claims del payload actual. */
    public function getPayload(): array {
        return $this->payload;
    }

    /** Devuelve un claim individual o null si no existe. */
    public function getPayloadValue(string $field): mixed {
        return $this->payload[$field] ?? null;
    }

    // ─── Core: Configuración lcobucci ────────────────────────

    /**
     * Crea (una sola vez) la configuración de lcobucci con
     * el algoritmo HMAC-SHA256 y la secretKey actual.
     */
    private function config(): Configuration {
        if (!isset($this->config)) {
            $this->config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($this->secretKey)
            );
        }
        return $this->config;
    }

    // ─── Core: Encode / Decode ────────────────────────────────

    /**
     * Genera un token JWT con los claims actuales.
     *
     * @param bool $refresh true → conserva jti (misma sesión), actualiza iat y exp.
     *                      false → genera todo nuevo.
     */
    public function encode(bool $refresh = false): string {
        $now = new DateTimeImmutable();

        $this->payload['iat'] = $now->getTimestamp();
        $this->payload['exp'] = $now->getTimestamp() + $this->expirationTime;

        if ($refresh || !isset($this->payload['iss'])) {
            if (!function_exists('base_url')) {
                helper('url');
            }
            $this->payload['iss'] = base_url();
        }

        $this->payload['jti'] ??= bin2hex(random_bytes(16));

        // Construir token con lcobucci (builder inmutable → reasignar)
        $builder = $this->config()->builder();
        $builder = $builder
            ->issuedBy($this->payload['iss'])
            ->issuedAt((new DateTimeImmutable())->setTimestamp($this->payload['iat']))
            ->expiresAt((new DateTimeImmutable())->setTimestamp($this->payload['exp']))
            ->identifiedBy($this->payload['jti']);

        // Claims estándar de lcobucci requieren métodos específicos
        foreach ($this->payload as $claim => $value) {
            if (in_array($claim, ['iss', 'exp', 'iat', 'jti', 'sub', 'aud', 'nbf'], true)) continue;
            $builder = $builder->withClaim($claim, $value);
        }

        if (isset($this->payload['sub'])) {
            $builder = $builder->relatedTo((string) $this->payload['sub']);
        }
        if (isset($this->payload['aud'])) {
            $aud = $this->payload['aud'];
            is_array($aud)
                ? $builder = $builder->permittedFor(...$aud)
                : $builder = $builder->permittedFor($aud);
        }
        if (isset($this->payload['nbf'])) {
            $builder = $builder->canOnlyBeUsedAfter(
                (new DateTimeImmutable())->setTimestamp($this->payload['nbf'])
            );
        }

        return $builder->getToken(
            $this->config()->signer(),
            $this->config()->signingKey()
        )->toString();
    }

    /**
     * Decodifica y verifica la firma de un token JWT.
     * Pobla $this->payload con todos los claims.
     *
     * @throws \RuntimeException Si la firma no es válida.
     */
    public function decode(string $value): self {
        $token = $this->config()->parser()->parse($value);

        // Validar firma HMAC-SHA256
        $constraint = new SignedWith(
            $this->config()->signer(),
            $this->config()->signingKey()
        );

        if (!$this->config()->validator()->validate($token, $constraint)) {
            throw new \RuntimeException('Invalid token signature');
        }

        // Extraer claims registrados (lcobucci los almacena con tipos)
        $this->payload = [];
        foreach (['iss', 'sub', 'aud', 'exp', 'nbf', 'iat', 'jti'] as $reg) {
            if ($token->claims()->has($reg)) {
                $val = $token->claims()->get($reg);
                $this->payload[$reg] = ($val instanceof \DateTimeImmutable)
                    ? $val->getTimestamp()
                    : $val;
            }
        }

        // Extraer claims personalizados
        foreach ($token->claims()->all() as $name => $claimValue) {
            if (!in_array($name, ['iss', 'sub', 'aud', 'exp', 'nbf', 'iat', 'jti'], true)) {
                $this->payload[$name] = $claimValue;
            }
        }

        return $this;
    }

    // ─── Auditoría ─────────────────────────────────────────────

    /**
     * Verifica que el claim "aud" del token coincida con el valor esperado.
     *
     * @throws AudRefusedException Si no coincide.
     */
    public function audit(string $audValue): self {
        $currentAud = $this->payload['aud'] ?? '';

        $matches = is_array($currentAud)
            ? in_array($audValue, $currentAud, true)
            : $currentAud === $audValue;

        if (!$matches) {
            $ex = new AudRefusedException('The audience refused');
            $ex->setPayload((object) $this->payload);
            throw $ex;
        }
        return $this;
    }

    // ─── Verificación Unificada O(1) ──────────────────────────

    /**
     * Valida si el token actual está revocado:
     * 1. Ámbito Específico (Token / Sesión: s.{jti})
     * 2. Ámbito Global     (Corte temporal por Sujeto: g.{subt}.{sub}) - opcional según $global
     *
     * @param bool $global true  → evalúa corte temporal por sujeto (tokens de sesión).
     *                     false → solo evalúa ámbito específico (tokens de acción / uso único).
     */
    public function isRevoked(bool $global = true): bool {
        $cache = service('cache');
        $jti   = $this->payload['jti'] ?? null;
        $iat   = (int) ($this->payload['iat'] ?? 0);

        // 1. Ámbito Específico (Token / Sesión)
        if (!empty($jti)) {
            $val = $cache->get("{$this->revocationKey}.s.{$jti}");
            if ($val !== null) {
                // -1 = invalidación completa (logout / token consumido)
                // numérico = timestamp del último token saliente tras refresh (invalida iat <= val)
                if ($val === -1 || (is_numeric($val) && $iat <= (int) $val)) {
                    return true;
                }
            }
        }

        // Si no se requiere evaluación global, termina aquí
        if (!$global) {
            return false;
        }

        // 2. Ámbito Global (Corte temporal por Sujeto)
        $sub = $this->payload['sub'] ?? null;
        if ($sub !== null && $iat > 0) {
            $subt = (string) ($this->payload['subt'] ?? '1');
            $revokedAt = $cache->get("{$this->revocationKey}.g.{$subt}.{$sub}");
            if ($revokedAt !== null && $iat <= (int) $revokedAt) {
                return true;
            }
        }

        return false;
    }

    // ─── Revocación Específica (Instancia) ─────────────────────

    /**
     * Revoca el token actual o la sesión completa en el ámbito específico (s.{jti}).
     *
     * @param bool $session false → revoca solo el token actual (refresh: guarda iat saliente).
     *                      true  → invalidación completa (logout / token consumido: guarda -1).
     */
    public function revoke(bool $session = false): bool {
        $jti = $this->payload['jti'] ?? null;
        if (empty($jti)) {
            return false;
        }

        $now = time();
        $exp = (int) ($this->payload['exp'] ?? 0);
        $ttl = ($exp > $now) ? ($exp - $now) : $this->expirationTime;

        $val = $session ? -1 : (int) ($this->payload['iat'] ?? $now);

        return (bool) service('cache')->save("{$this->revocationKey}.s.{$jti}", $val, $ttl);
    }

    // ─── Revocación Global (Estática) ─────────────────────────

    /**
     * Revoca todos los tokens emitidos antes de este instante para un sujeto (g.{subt}.{sub}).
     * El TTL se deduce automáticamente según la duración máxima configurada para el tipo de sujeto.
     */
    public static function revokeGlobal(string|int $subject, string $subjectType = '1'): bool {
        $jwtConfig = config('Jwt');
        $prefix = $jwtConfig->authRevocationKey ?? 'jwt_auth_rev';
        $ttl    = ($subjectType === '2')
            ? ($jwtConfig->clientTokenDuration ?? 10800)
            : ($jwtConfig->userTokenDuration ?? 2700);

        $key = "{$prefix}.g.{$subjectType}.{$subject}";
        return (bool) service('cache')->save($key, time(), $ttl);
    }
}
