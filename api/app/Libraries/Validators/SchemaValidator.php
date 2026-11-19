<?php

namespace App\Libraries\Validators;

use DancasDev\SchemaValidator\SchemaValidator as BaseValidator;

/**
 * Validador de esquemas JSON de la aplicación.
 *
 * Extiende DancasDev\SchemaValidator precargando los mensajes de idioma
 * y el tipo custom 'i18n' basado en los locales de `Config\App::$supportedLocales`.
 */
class SchemaValidator extends BaseValidator {
    public function __construct(array $types = [], array $messages = []) {
        parent::__construct(
            array_merge(['i18n' => self::i18nType()], $types),
            array_merge(lang('SchemaValidator.messages') ?? [], $messages)
        );
    }

    /**
     * Validador del tipo 'i18n'.
     *
     * Valida que el valor sea un arreglo asociativo cuyas claves sean
     * locales soportados en `Config\App::$supportedLocales` y sus valores texto.
     */
    public static function i18nType(): \Closure {
        return static function ($value, array $rules): ?string {
            if (!is_array($value) || array_is_list($value) || empty($value)) {
                return lang('SchemaValidator.messages.i18n');
            }

            $supportedLocales = config('App')->supportedLocales ?? [];

            $max = $rules['max_length'] ?? null;
            $min = $rules['min_length'] ?? null;

            foreach ($value as $langCode => $text) {
                if (!in_array($langCode, $supportedLocales, true)) {
                    return lang('SchemaValidator.messages.i18n_lang', [$langCode]);
                }

                if (!is_string($text)) {
                    return lang('SchemaValidator.messages.i18n_string', [$langCode]);
                }

                if ($max !== null && mb_strlen($text) > (int) $max) {
                    return lang('SchemaValidator.messages.i18n_max_length', [$langCode, $max]);
                }

                if ($min !== null && mb_strlen($text) < (int) $min) {
                    return lang('SchemaValidator.messages.i18n_min_length', [$langCode, $min]);
                }
            }

            return null;
        };
    }
}
