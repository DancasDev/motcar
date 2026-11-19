<?php

declare(strict_types=1);

/**
 * Extensión de Helper de Texto para Motcar API.
 */

if (! function_exists('word_limiter') && defined('SYSTEMPATH') && file_exists(SYSTEMPATH . 'Helpers/text_helper.php')) {
    require_once SYSTEMPATH . 'Helpers/text_helper.php';
}

if (! function_exists('format_identification')) {
    /**
     * Formatea el tipo y valor de identificación según el estándar "{t}.- {v}".
     *
     * Tipos según glb_person: 0=V, 1=E, 2=J, 3=P.
     */
    function format_identification(string|int $type, ?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $types     = ['0' => 'V', '1' => 'E', '2' => 'J', '3' => 'P'];
        $letter    = $types[(string) $type] ?? 'V';
        $formatted = in_array($letter, ['V', 'E'], true) && is_numeric($value)
            ? number_format((float) $value, 0, '', '.')
            : $value;

        return "{$letter}.- {$formatted}";
    }
}
