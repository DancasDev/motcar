<?php

namespace App\Libraries\Telegram\Actions;

use App\Libraries\Telegram\TelegramBotService;
use App\Libraries\Telegram\TelegramContext;
use App\Models\Gac\UserModel;
use App\Models\System\BinnacleModel;

/**
 * Acción para desvincular la cuenta del chat de Telegram.
 */
class UnbindAction implements TelegramActionInterface
{
    public function handle(TelegramContext $ctx, TelegramBotService $bot): void
    {
        $chatId = $ctx->getChatId();
        $user = $ctx->getBoundUser();
        $locale = $ctx->getLocale();

        if ($user === null) {
            $bot->sendMessage($chatId, lang('Telegram.unbind_not_bound', [], $locale));
            return;
        }

        // Desvincular el chat y simultáneamente desactivar las notificaciones por canal 1 (Telegram)
        $model = new UserModel();
        $model->skipValidation(true)->protect(false)
            ->update($user['id'], [
                'telegram_chat_id'      => null,
                'telegram_username'     => null,
                'notification_channels' => $model->removeNotificationChannels($user['notification_channels'], ['1']),
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

        (new BinnacleModel())->log(
            'user',
            (int) $user['id'],
            'telegram',
            (string) $chatId,
            'delete',
            date('Y-m-d H:i:s'),
            service('request')->getIPAddress(),
            [
                'chat_id'      => $chatId,
                'channel_type' => '1',
            ],
            null
        );

        $ctx->setBoundUser(null);

        $bot->sendMessage($chatId, lang('Telegram.unbind_success', [], $locale));
    }
}
