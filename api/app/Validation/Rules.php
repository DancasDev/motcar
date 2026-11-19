<?php

namespace App\Validation;

class Rules {
    /**
     * Caché en memoria para evitar consultas duplicadas durante la validación de una petición.
     * Al ser propiedad de instancia, se limpia automáticamente cuando el ciclo de vida del
     * validador finaliza al término de cada request HTTP (incluso en FrankenPHP / Worker Mode).
     */
    protected array $multiConditionCache = [];

    public function is_not_unique_multi_condition($str, string $field, array $data, ?string &$error = null, ?string $currentField = null): bool {
        if ($str === null || $str === '') {
            return true;
        }

        [$tableFieldPath, $extraConditions] = array_pad(explode(',', $field), 2, '');

        $parts = explode('.', $tableFieldPath, 3);
        if (count($parts) === 3) {
            [$dbGroup, $table, $column] = $parts;
        } elseif (count($parts) === 2) {
            [$table, $column] = $parts;
            $dbGroup = null;
        } else {
            throw new \InvalidArgumentException('Must be "table.field" or "dbGroup.table.field"');
        }

        $dbGroup ??= $data['DBGroup'] ?? null;

        // Parsear y resolver condiciones dinámicas de forma canónica
        $resolvedConditions = [];
        if (!empty($extraConditions)) {
            foreach (explode(';', $extraConditions) as $condition) {
                $parts = explode(':', $condition, 2);
                if (count($parts) < 2) continue;
                [$whereField, $whereValue] = $parts;
                if (str_starts_with($whereValue, '{') && array_key_exists($key = trim($whereValue, '{}'), $data)) {
                    $whereValue = $data[$key];
                }
                $resolvedConditions[$whereField] = $whereValue;
            }
            ksort($resolvedConditions);
        }

        // Clave única determinista en RAM
        $cacheKey = md5(json_encode([$dbGroup, $table, $column, (string)$str, $resolvedConditions]));

        if (array_key_exists($cacheKey, $this->multiConditionCache)) {
            $exists = $this->multiConditionCache[$cacheKey];
            if (!$exists) {
                $error = lang('Validation.is_not_unique', ['field' => $currentField]);
            }
            return $exists;
        }

        $builder = db_connect($dbGroup)
            ->table($table)
            ->select('1')
            ->where($column, $str)
            ->limit(1);

        foreach ($resolvedConditions as $whereField => $whereValue) {
            match ($whereValue) {
                'IS_NULL'     => $builder->where($whereField, null),
                'IS_NOT_NULL' => $builder->where("{$whereField} !=", null),
                default       => $builder->where($whereField, $whereValue),
            };
        }

        $exists = ($builder->get()->getRow() !== null);
        $this->multiConditionCache[$cacheKey] = $exists;

        if (!$exists) {
            $error = lang('Validation.is_not_unique', ['field' => $currentField]);
        }

        return $exists;
    }

    /**
     * Valida que una fecha/hora sea estrictamente mayor que otra fecha o campo.
     *
     * @param mixed       $str          Valor de fecha/hora a validar.
     * @param string      $param        Nombre de otro campo ({field}) o fecha/hora fija.
     * @param array       $data         Datos del formulario/payload.
     * @param string|null $error        Mensaje de error por referencia.
     * @param string|null $currentField Nombre del campo actual.
     */
    public function date_greater_than($str, string $param, array $data, ?string &$error = null, ?string $currentField = null): bool {
        if ($str === null || $str === '') {
            return true;
        }

        $timeA = strtotime((string) $str);
        if ($timeA === false) {
            $error = lang('Validation.valid_date');
            return false;
        }

        $compareVal = array_key_exists($param, $data) ? $data[$param] : $param;
        if ($compareVal === null || $compareVal === '') {
            return true;
        }

        $timeB = strtotime((string) $compareVal);
        if ($timeB === false) {
            return true;
        }

        if ($timeA > $timeB) {
            return true;
        }

        $error = lang('Validation.date_greater_than', ['param' => $param]);
        return false;
    }

    /**
     * Valida que una fecha/hora sea mayor o igual que otra fecha o campo.
     */
    public function date_greater_than_equal_to($str, string $param, array $data, ?string &$error = null, ?string $currentField = null): bool {
        if ($str === null || $str === '') {
            return true;
        }

        $timeA = strtotime((string) $str);
        if ($timeA === false) {
            $error = lang('Validation.valid_date');
            return false;
        }

        $compareVal = array_key_exists($param, $data) ? $data[$param] : $param;
        if ($compareVal === null || $compareVal === '') {
            return true;
        }

        $timeB = strtotime((string) $compareVal);
        if ($timeB === false) {
            return true;
        }

        if ($timeA >= $timeB) {
            return true;
        }

        $error = lang('Validation.date_greater_than_equal_to', ['param' => $param]);
        return false;
    }

    /**
     * Valida que una fecha/hora sea estrictamente menor que otra fecha o campo.
     */
    public function date_less_than($str, string $param, array $data, ?string &$error = null, ?string $currentField = null): bool {
        if ($str === null || $str === '') {
            return true;
        }

        $timeA = strtotime((string) $str);
        if ($timeA === false) {
            $error = lang('Validation.valid_date');
            return false;
        }

        $compareVal = array_key_exists($param, $data) ? $data[$param] : $param;
        if ($compareVal === null || $compareVal === '') {
            return true;
        }

        $timeB = strtotime((string) $compareVal);
        if ($timeB === false) {
            return true;
        }

        if ($timeA < $timeB) {
            return true;
        }

        $error = lang('Validation.date_less_than', ['param' => $param]);
        return false;
    }

    /**
     * Valida que una fecha/hora sea menor o igual que otra fecha o campo.
     */
    public function date_less_than_equal_to($str, string $param, array $data, ?string &$error = null, ?string $currentField = null): bool {
        if ($str === null || $str === '') {
            return true;
        }

        $timeA = strtotime((string) $str);
        if ($timeA === false) {
            $error = lang('Validation.valid_date');
            return false;
        }

        $compareVal = array_key_exists($param, $data) ? $data[$param] : $param;
        if ($compareVal === null || $compareVal === '') {
            return true;
        }

        $timeB = strtotime((string) $compareVal);
        if ($timeB === false) {
            return true;
        }

        if ($timeA <= $timeB) {
            return true;
        }

        $error = lang('Validation.date_less_than_equal_to', ['param' => $param]);
        return false;
    }

    /**
     * Valida que un campo contenga una estructura i18n válida (array asociativo de idioma => texto).
     *
     * Aplica whitelist estricta basada en Config\App::$supportedLocales.
     * Cualquier clave que no sea un idioma soportado es rechazada.
     *
     * Sintaxis:
     *   valid_i18n
     *   valid_i18n[min_length:1,max_length:120]
     *   valid_i18n[120]
     *   valid_i18n[1,120]
     *
     * @param mixed       $value  Valor a validar.
     * @param string|null $params Parámetros de longitud opcionales.
     * @param array       $data   Todo el payload recibido.
     * @param string|null $error  Mensaje de error retornado por referencia.
     */
    public function valid_i18n($value, ?string $params = null, array $data = [], ?string &$error = null): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $supportedLocales = config('App')->supportedLocales ?? [];

        // Debe ser un array asociativo, no una lista numérica ni estar vacío
        if (!is_array($value) || array_is_list($value) || empty($value)) {
            $error = lang('Validation.valid_i18n');
            return false;
        }

        // Whitelist: no puede contener más claves que la cantidad de idiomas configurados
        if (count($value) > count($supportedLocales)) {
            $error = lang('Validation.valid_i18n');
            return false;
        }

        $minLength = null;
        $maxLength = null;

        if (!empty($params)) {
            foreach (explode(',', $params) as $part) {
                if (str_contains($part, ':')) {
                    [$k, $v] = explode(':', $part, 2);
                    $k = trim($k);
                    if ($k === 'min_length' || $k === 'min') {
                        $minLength = (int) trim($v);
                    } elseif ($k === 'max_length' || $k === 'max') {
                        $maxLength = (int) trim($v);
                    }
                } elseif (is_numeric(trim($part))) {
                    if ($maxLength === null && !str_contains($params, ',')) {
                        $maxLength = (int) trim($part);
                    } elseif ($minLength === null) {
                        $minLength = (int) trim($part);
                    } else {
                        $maxLength = (int) trim($part);
                    }
                }
            }
        }

        foreach ($value as $langCode => $text) {
            // Rechaza cualquier clave que no esté en la lista blanca de idiomas
            if (!in_array((string) $langCode, $supportedLocales, true)) {
                $error = lang('Validation.valid_i18n_lang', [(string) $langCode]);
                return false;
            }

            // Debe ser texto no vacío
            if (!is_string($text) || trim($text) === '') {
                $error = lang('Validation.valid_i18n_string', [$langCode]);
                return false;
            }

            $length = mb_strlen($text);

            if ($maxLength !== null && $length > $maxLength) {
                $error = lang('Validation.valid_i18n_max_length', [$langCode, $maxLength]);
                return false;
            }

            if ($minLength !== null && $length < $minLength) {
                $error = lang('Validation.valid_i18n_min_length', [$langCode, $minLength]);
                return false;
            }
        }

        return true;
    }

    /**
     * Valida que un valor sea una URL válida (o un array de URLs válidas).
     * Soporta tanto string único como array de URLs para campos de almacenamiento o archivos múltiples.
     *
     * @param mixed       $value        Valor a validar (string o array de strings).
     * @param string|null $validSchemes Esquemas válidos separados por coma (default: http,https).
     * @param array       $data         Todo el payload recibido.
     * @param string|null $error        Mensaje de error retornado por referencia.
     */
    public function valid_url_strict($value = null, ?string $validSchemes = null, array $data = [], ?string &$error = null): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $validSchemesList = explode(',', strtolower($validSchemes ?? 'http,https'));

        $checkUrl = function ($url) use ($validSchemesList): bool {
            if (!is_string($url) || trim($url) === '') {
                return false;
            }
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            return in_array($scheme, $validSchemesList, true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
        };

        if (is_array($value)) {
            if (empty($value)) {
                $error = lang('Validation.valid_url');
                return false;
            }
            foreach ($value as $item) {
                if (!$checkUrl($item)) {
                    $error = lang('Validation.valid_url');
                    return false;
                }
            }
            return true;
        }

        if (!$checkUrl($value)) {
            $error = lang('Validation.valid_url');
            return false;
        }

        return true;
    }
}
