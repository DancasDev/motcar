<?php

namespace App\Libraries\Comms\Drivers;

/**
 * Contrato para drivers que soportan despacho masivo y concurrente (ej. curl_multi en Push).
 */
interface BatchDriverInterface extends DriverInterface
{
    /**
     * Envía un lote de mensajes concurrentemente.
     *
     * @param array<int|string, array{token?: string, to?: string, title: string, message: string, data?: array, options?: array}> $items
     * @return array<int|string, array{success: bool, error?: string, message_id?: string}>
     */
    public function sendBatch(array $items): array;
}
