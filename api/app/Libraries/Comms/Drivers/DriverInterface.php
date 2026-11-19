<?php

namespace App\Libraries\Comms\Drivers;

use App\Libraries\Comms\Exceptions\DeliveryFailedException;

/**
 * Contrato base para todo driver de transporte físico de comunicaciones.
 */
interface DriverInterface
{
    /**
     * Envía un mensaje directo a un destinatario.
     *
     * @param string|array $to      Destinatario (correo, push token, chat ID o array según driver)
     * @param string       $title   Título, asunto o encabezado
     * @param string|array $message Cuerpo del mensaje (texto, HTML o array estructurado)
     * @param array        $options Opciones adicionales (vistas, metadatos, botones, etc.)
     *
     * @throws DeliveryFailedException Si la entrega falla
     */
    public function send(string|array $to, string $title, string|array $message, array $options = []): bool;
}
