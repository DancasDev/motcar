<?php

namespace App\Libraries\Comms\Outbox;

/**
 * Utilidad para interpolar variables dinámicas {variable} en plantillas de texto de notificaciones.
 */
class NotificationInterpolator
{
    /**
     * Interpola variables {clave} en un string de plantilla.
     *
     * @param string               $template Texto con marcadores tipo {variable}
     * @param array<string, mixed> $data     Mapa de sustituciones clave => valor
     */
    public static function interpolate(string $template, array $data): string
    {
        return preg_replace_callback('/\{(\w+)\}/', static function ($matches) use ($data) {
            $key = $matches[1];
            return isset($data[$key]) ? (string) $data[$key] : $matches[0];
        }, $template) ?? $template;
    }
}
