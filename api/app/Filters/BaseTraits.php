<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

trait BaseTraits {
    protected array $setting = [];

    /**
     * Parsea los argumentos del filtro definidos en la ruta.
     *
     * Formato: 'auth:module_code=users,only_user'
     * Soporta: true, false, null y string.
     * Solo se aceptan keys que existan en $this->setting.
     */
    protected function initSetting(?array $arguments): array {
        if (is_array($arguments)) {
            foreach ($arguments as $arg) {
                $arg = explode('=', $arg);
                if (!array_key_exists($arg[0], $this->setting)) continue;

                if (empty($arg[1]) || $arg[1] === 'true') {
                    $this->setting[$arg[0]] = true;
                } elseif ($arg[1] === 'false') {
                    $this->setting[$arg[0]] = false;
                } elseif ($arg[1] === 'null') {
                    $this->setting[$arg[0]] = null;
                } else {
                    $this->setting[$arg[0]] = $arg[1];
                }
            }
        }

        return $this->setting;
    }

    /**
     * Respuesta HTTP exitosa en JSON.
     */
    protected function respond(mixed $data, int $status = 200): ResponseInterface {
        return service('response')->setJson($data)->setStatusCode($status);
    }

    /**
     * Respuesta HTTP de error en JSON.
     * Formato: {status, error, messages}
     */
    protected function fail(mixed $messages, int $status = 400, ?string $code = null): ResponseInterface {
        return $this->respond([
            'status'   => $status,
            'error'    => $code ?? $status,
            'messages' => is_array($messages) ? $messages : ['error' => $messages],
        ], $status);
    }

}
