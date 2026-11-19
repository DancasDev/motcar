<?php

namespace App\Libraries\Telegram\Actions;

use App\Libraries\Telegram\TelegramBotService;
use App\Libraries\Telegram\TelegramContext;

/**
 * Contrato estándar para todas las acciones y comandos del bot de Telegram.
 */
interface TelegramActionInterface
{
    /**
     * Ejecuta la acción en respuesta a un comando o mensaje del usuario.
     */
    public function handle(TelegramContext $ctx, TelegramBotService $bot): void;
}
