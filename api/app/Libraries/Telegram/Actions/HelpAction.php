<?php

namespace App\Libraries\Telegram\Actions;

use App\Libraries\Telegram\TelegramBotService;
use App\Libraries\Telegram\TelegramContext;

/**
 * Acción para el comando /ayuda o /help.
 */
class HelpAction implements TelegramActionInterface
{
    public function handle(TelegramContext $ctx, TelegramBotService $bot): void
    {
        $chatId = $ctx->getChatId();
        $isBound = $ctx->isBound();
        $locale = $ctx->getLocale();

        $header = lang('Telegram.help_header', [], $locale);
        $footer = $isBound
            ? lang('Telegram.help_bound', [], $locale)
            : lang('Telegram.help_unbound', [], $locale);

        $bot->sendMessage($chatId, $header . $footer);
    }
}
