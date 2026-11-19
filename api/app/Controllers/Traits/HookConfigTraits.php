<?php

namespace App\Controllers\Traits;

/**
 * Proporciona manejo unificado de callbacks y configuraciones para traits operacionales.
 *
 * Cada trait hijo registra sus hooks y configs válidos mediante un método privado
 * con la nomenclatura __registerHookConfig_{sufijo}. HookConfigTraits los descubre
 * automáticamente mediante reflexión la primera vez que se invoca cualquier método
 * público, y lanza una excepción si dos traits intentan registrar la misma clave.
 */
trait HookConfigTraits {
    /**
     * Registro único con origen y metadatos de validación.
     *
     * Estructura:
     *   'callbacks' => ['cb_name' => ['source' => 'TraitName']]
     *   'config'    => ['cfg_key'  => ['source' => 'TraitName', 'types' => [...], 'default' => ...]]
     */
    private array $hooksRegistry = [
        'callbacks' => [],
        'config'    => [],
    ];

    /** Valores en runtime */
    private array $callbacks = [];
    private array $config = [];

    private bool $hooksInitialized = false;

    /**
     * Registra callbacks válidos para un trait.
     *
     * @param string $source Nombre del trait que registra (ej: __TRAIT__).
     * @param string[] $list Lista de nombres de callbacks.
     */
    protected function registerCallbacks(string $source, array $list): void {
        foreach ($list as $cb) {
            if (isset($this->hooksRegistry['callbacks'][$cb])) {
                continue;
            }
            $this->hooksRegistry['callbacks'][$cb] = ['source' => $source];
        }
    }

    /**
     * Registra configuraciones válidas para un trait.
     *
     * @param string $source Nombre del trait que registra (ej: __TRAIT__).
     * @param array $defs Lista de definiciones [clave => ['types' => [...], 'default' => ...]].
     */
    protected function registerConfig(string $source, array $defs): void {
        foreach ($defs as $key => $def) {
            if (isset($this->hooksRegistry['config'][$key])) {
                $existing = $this->hooksRegistry['config'][$key];
                if ($this->configDefsDiffer($existing, $def)) {
                    throw new \LogicException(
                        "La configuración '{$key}' ya fue registrada por '{$existing['source']}' "
                        . "con una definición diferente. "
                        . "'{$source}' intentó registrarla como: "
                        . json_encode($def, JSON_UNESCAPED_UNICODE)
                    );
                }
                continue;
            }
            $this->hooksRegistry['config'][$key] = array_merge(
                ['source' => $source],
                $def
            );
        }
    }

    private function configDefsDiffer(array $existing, array $incoming): bool {
        $existingTypes = $existing['types'] ?? null;
        $incomingTypes = $incoming['types'] ?? null;

        if ($existingTypes !== $incomingTypes) {
            return true;
        }

        $existingDefault = array_key_exists('default', $existing) ? $existing['default'] : new \stdClass();
        $incomingDefault = array_key_exists('default', $incoming) ? $incoming['default'] : new \stdClass();

        return $existingDefault !== $incomingDefault;
    }

    /**
     * Descubre y ejecuta automáticamente los métodos __registerHookConfig_*
     * de todos los traits que usan HookConfigTraits.
     */
    private function ensureHooksInitialized(): void {
        if ($this->hooksInitialized) {
            return;
        }
        $this->hooksInitialized = true;

        $ref = new \ReflectionObject($this);
        foreach ($ref->getMethods(\ReflectionMethod::IS_PRIVATE) as $method) {
            if (str_starts_with($method->name, '__registerHookConfig_')) {
                $method->setAccessible(true);
                $method->invoke($this);
            }
        }
    }

    // ----------------------------------------------------------------
    // Callbacks
    // ----------------------------------------------------------------

    /**
     * Establece uno o varios callbacks para hooks del ciclo de vida.
     *
     * @param string|array $key Nombre del hook o array [hook => callable].
     * @param callable|null $callback La función a ejecutar (si $key es string).
     */
    public function setCallback(string|array $key, ?callable $callback = null): void {
        $this->ensureHooksInitialized();

        $list = is_array($key) ? $key : [$key => $callback];
        foreach ($list as $k => $cb) {
            $def = $this->hooksRegistry['callbacks'][$k] ?? null;
            if (!$def) {
                $disponibles = empty($this->hooksRegistry['callbacks'])
                    ? '(ninguno registrado)'
                    : implode(', ', array_keys($this->hooksRegistry['callbacks']));
                throw new \InvalidArgumentException(
                    "El callback '{$k}' no es válido. Callbacks disponibles: {$disponibles}."
                );
            }
            if (!is_callable($cb)) {
                throw new \InvalidArgumentException(
                    "El callback '{$k}' debe ser una función."
                );
            }
            $this->callbacks[$k] = $cb;
        }
    }

    public function getCallback(string $key): ?callable {
        $this->ensureHooksInitialized();
        return $this->callbacks[$key] ?? null;
    }

    public function hasCallback(string $key): bool {
        $this->ensureHooksInitialized();
        return isset($this->callbacks[$key]);
    }

    /**
     * Ejecuta un callback registrado y devuelve su resultado.
     * En caso de excepción, registra el error en el log y retorna una respuesta HTTP 500.
     */
    public function runCallback(string $key, mixed ...$params): mixed {
        $this->ensureHooksInitialized();

        try {
            return $this->callbacks[$key](...$params);
        } catch (\Throwable $e) {
            $source = $this->hooksRegistry['callbacks'][$key]['source'] ?? 'desconocido';

            log_message('error', "[HOOK_CALLBACK_ERROR] {exception}\n"
                . "Trait origen:\t{source}\n"
                . "Callback:\t{key}\n"
                . "Params:\t{params}\n"
                . "Stack trace:\n{stackTrace}", [
                'exception' => $e,
                'source'    => $source,
                'key'       => $key,
                'params'    => json_encode($params),
                'stackTrace'=> $e->getTraceAsString(),
            ]);
            return $this->fail(lang('HookConfigTraits.callback.error', [$key]), 500, 'HOOK_CALLBACK_ERROR');
        }
    }

    // ----------------------------------------------------------------
    // Configuración
    // ----------------------------------------------------------------

    /**
     * Establece uno o varios valores de configuración.
     *
     * @param string|array $key Clave o array [clave => valor].
     * @param mixed $value El valor (si $key es string).
     */
    public function setConfig(string|array $key, mixed $value = null): void {
        $this->ensureHooksInitialized();

        $list = is_array($key) ? $key : [$key => $value];
        foreach ($list as $k => $v) {
            $def = $this->hooksRegistry['config'][$k] ?? null;
            if (!$def) {
                $disponibles = empty($this->hooksRegistry['config'])
                    ? '(ninguno registrado)'
                    : implode(', ', array_keys($this->hooksRegistry['config']));
                throw new \InvalidArgumentException(
                    "La configuración '{$k}' no es válida. Configuraciones disponibles: {$disponibles}."
                );
            }

            $types = $def['types'] ?? null;
            if (is_array($types)) {
                $type = gettype($v);
                if (!in_array($type, $types, true)) {
                    $esperados = implode(', ', $types);
                    throw new \InvalidArgumentException(
                        "El valor para la configuración '{$k}' debe ser de tipo: {$esperados}. "
                        . "Tipo recibido: {$type}."
                    );
                }
            }

            $this->config[$k] = $v;
        }
    }

    public function getConfig(string $key): mixed {
        $this->ensureHooksInitialized();

        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }
        return $this->hooksRegistry['config'][$key]['default'] ?? null;
    }

    public function hasConfig(string $key): bool {
        $this->ensureHooksInitialized();
        return isset($this->config[$key]);
    }
}
