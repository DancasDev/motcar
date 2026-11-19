<?php

namespace App\Libraries\Telegram\Actions;

use App\Libraries\Telegram\TelegramBotService;
use App\Libraries\Telegram\TelegramContext;

/**
 * Acción para consultar el estado de la vinculación (/estado o /status).
 */
class StatusAction implements TelegramActionInterface
{
    public function handle(TelegramContext $ctx, TelegramBotService $bot): void
    {
        $chatId = $ctx->getChatId();
        $user = $ctx->getBoundUser();
        $locale = $ctx->getLocale();

        if ($user === null) {
            $bot->sendMessage($chatId, lang('Telegram.status_unbound', [], $locale));
            return;
        }

        $channels = explode(',', (string) ($user['notification_channels'] ?? ''));
        $telegramActive = in_array('1', $channels, true);
        
        $notifStatus = $telegramActive
            ? lang('Telegram.channel_active', [], $locale)
            : lang('Telegram.channel_inactive', [], $locale);

        $message = lang('Telegram.status_bound', [$notifStatus], $locale);
        $bot->sendMessage($chatId, $message);
    }
}
