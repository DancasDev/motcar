<?php

namespace App\Libraries\JWT;

/**
 * Excepción lanzada cuando el "aud" del token no coincide
 * con el valor esperado (usuario o cliente actual).
 *
 * Transporta el payload del token en getPayload() para que
 * el manejador pueda inspeccionar los claims incluso cuando
 * la auditoría falla.
 */
class AudRefusedException extends \UnexpectedValueException {
    private object $payload;

    public function setPayload(object $payload): void {
        $this->payload = $payload;
    }

    public function getPayload(): object {
        return $this->payload;
    }
}
