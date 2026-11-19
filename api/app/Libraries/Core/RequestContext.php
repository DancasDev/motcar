<?php

namespace App\Libraries\Core;

/**
 * RequestContext
 *
 * Contenedor de estado en memoria para el ciclo de vida de la petición HTTP actual.
 * Proporciona un almacenamiento clave-valor uniforme y tipado para compartir datos
 * entre filtros, servicios y controladores sin depender de $GLOBALS ni singletons con fugas.
 *
 * Los nombres de las claves se estructuran con prefijos por dominio:
 * - auth.*    : Datos de autenticación y autorización (usuario, cliente, payload, scope, permiso).
 * - request.* : Metadatos derivados de la petición HTTP (acción procesada, flags de cabecera como in_trash).
 * - token.*   : Tokens de acción de un solo uso o transitorios.
 *
 * En entornos persistentes como FrankenPHP (worker mode), se reinicia al inicio
 * y al final de cada petición mediante eventos del sistema para evitar state pollution.
 */
final class RequestContext {
    // Dominio auth:*
    public const AUTH_USER_ID     = 'auth:userId';
    public const AUTH_CLIENT_ID   = 'auth:clientId';
    public const AUTH_SCOPE_PATH  = 'auth:scopePath';
    public const AUTH_JWT_PAYLOAD = 'auth:jwtPayload';
    public const AUTH_PERMISSION  = 'auth:permission';

    // Dominio request:*
    public const REQUEST_ACTION   = 'request:action';
    public const REQUEST_IN_TRASH = 'request:inTrash';

    // Dominio token:*
    public const TOKEN_ACTION     = 'token:action';

    private array $data = [];

    /**
     * Guarda un valor en el contexto de la petición.
     */
    public function set(string $key, mixed $value): self {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Obtiene un valor por clave o el valor por defecto si no existe.
     */
    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    /**
     * Determina si existe una clave en el contexto actual.
     */
    public function has(string $key): bool {
        return array_key_exists($key, $this->data);
    }

    /**
     * Remueve una clave específica del contexto.
     */
    public function remove(string $key): self {
        unset($this->data[$key]);
        return $this;
    }

    /**
     * Reinicia y vacía todo el estado de la petición.
     * Esencial para evitar fugas de memoria y contaminación entre peticiones en FrankenPHP.
     */
    public function reset(): void {
        $this->data = [];
    }

    /**
     * Retorna todas las claves y valores almacenados en la petición actual.
     */
    public function all(): array {
        return $this->data;
    }
}
