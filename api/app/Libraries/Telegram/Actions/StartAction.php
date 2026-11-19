<?php

namespace App\Libraries\Telegram\Actions;

use App\Libraries\Telegram\TelegramBotService;
use App\Libraries\Telegram\TelegramContext;

/**
 * Acción para el comando /start.
 */
class StartAction implements TelegramActionInterface
{
    public function handle(TelegramContext $ctx, TelegramBotService $bot): void
    {
        $text = (string) $ctx->getText();
        $payload = trim(substr($text, 6));

        // Si el payload contiene un token JWT, delegar a BindAction
        if (!empty($payload) && substr_count($payload, '.') === 2) {
            (new BindAction())->handle($ctx, $bot);
            return;
        }

        $chatId = $ctx->getChatId();
        $locale = $ctx->getLocale();

        if ($ctx->isBound()) {
            $bot->sendMessage($chatId, lang('Telegram.start_bound', [], $locale));
            return;
        }

        $bot->sendMessage($chatId, lang('Telegram.start_unbound', [], $locale));
    }
}
