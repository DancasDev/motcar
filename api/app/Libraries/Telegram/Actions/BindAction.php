<?php

namespace App\Libraries\Telegram\Actions;

use App\Libraries\Telegram\TelegramBotService;
use App\Libraries\Telegram\TelegramContext;
use App\Models\Gac\UserModel;
use App\Models\System\BinnacleModel;

/**
 * Acción para vincular una cuenta de usuario con el chat de Telegram.
 */
class BindAction implements TelegramActionInterface
{
    private const SUBSCRIPTION_AUD = 'notification_subscription';

    public function handle(TelegramContext $ctx, TelegramBotService $bot): void
    {
        $chatId = $ctx->getChatId();
        $messageId = $ctx->getMessageId();
        $username = $ctx->getTelegramUsername();
        $locale = $ctx->getLocale();

        $token = $this->extractToken($ctx, $bot);
        if ($token === null) {
            $bot->sendMessage($chatId, lang('Telegram.token_invalid', [], $locale));
            return;
        }

        // Eliminar el mensaje del usuario que contiene el token para mantener el chat limpio y seguro
        if ($messageId !== null) {
            $bot->deleteMessage($chatId, $messageId);
        }

        $error = $this->executeBinding($chatId, $token, $username, $ctx);
        if ($error !== null) {
            $bot->sendMessage($chatId, $error);
            return;
        }

        // Obtener el mensaje en el idioma configurado para el usuario
        $bot->sendMessage($chatId, lang('Telegram.bind_success', [], $ctx->getLocale()));
    }

    /**
     * Extrae el token JWT del contexto (texto o deep-link).
     */
    public function extractToken(TelegramContext $ctx, ?TelegramBotService $bot = null): ?string
    {
        $text = $ctx->getText();
        if (!is_string($text)) {
            return null;
        }

        $clean = trim($text);

        // Si viene con /start <token> o /vincular <token>
        if (preg_match('/^\/(?:start|vincular)\s+(.+)$/i', $clean, $m)) {
            $candidate = trim($m[1]);
            if ($this->looksLikeJwt($candidate)) {
                return $candidate;
            }
        }

        // Si viene en parámetro URL start=...
        if (preg_match('/[?&]start=([^&\s]+)/', $clean, $m)) {
            $candidate = urldecode($m[1]);
            if ($this->looksLikeJwt($candidate)) {
                return $candidate;
            }
        }

        // Si viene el token directamente
        if ($this->looksLikeJwt($clean)) {
            return $clean;
        }

        return null;
    }

    private function executeBinding(string $chatId, string $jwt, ?string $telegramUsername, TelegramContext $ctx): ?string
    {
        $token = service('actionToken');

        try {
            $token->decode($jwt);
        } catch (\Throwable $e) {
            return lang('Telegram.token_invalid', [], $ctx->getLocale());
        }

        if (time() > (int) $token->getPayloadValue('exp')) {
            return lang('Telegram.token_expired', [], $ctx->getLocale());
        }
        if ($token->isRevoked(false)) {
            return lang('Telegram.token_used', [], $ctx->getLocale());
        }

        $aud = $token->getPayloadValue('aud');
        $audOk = is_array($aud)
            ? in_array(self::SUBSCRIPTION_AUD, $aud, true)
            : $aud === self::SUBSCRIPTION_AUD;

        if (!$audOk || $token->getPayloadValue('subt') !== '1' || $token->getPayloadValue('act') !== 'telegram_bind') {
            return lang('Telegram.token_invalid', [], $ctx->getLocale());
        }

        $userId = (int) $token->getPayloadValue('sub');
        $user = (new UserModel())
            ->select('id, username, first_name, last_name, email, telegram_chat_id, notification_channels, language_id')
            ->where('id', $userId)
            ->where('is_disabled', '0')
            ->where('deleted_at', null)
            ->first();

        if ($user === null) {
            return lang('Telegram.token_invalid', [], $ctx->getLocale());
        }

        // Si este chat_id ya pertenecía a otro usuario, desvincularlo primero
        $this->unbindExistingChat($chatId);

        // La vinculación asocia el chat al usuario SIN forzar la activación de canales de notificación.
        // La activación del canal de notificaciones se gestiona de forma independiente.
        $model = new UserModel();
        $model->skipValidation(true)->protect(false)
            ->update($user['id'], [
                'telegram_chat_id'  => $chatId,
                'telegram_username' => $telegramUsername,
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);

        $token->revoke(true);
        $prefix = config('Telegram')->bindingCachePrefix ?: 'tmp_tg_sub_';
        cache()->delete($prefix . $userId);

        // Actualizar contexto con el usuario vinculado
        $user['telegram_chat_id'] = $chatId;
        $user['telegram_username'] = $telegramUsername;
        $ctx->setBoundUser($user);

        (new BinnacleModel())->log(
            'user',
            $userId,
            'telegram',
            (string) $chatId,
            'create',
            date('Y-m-d H:i:s'),
            service('request')->getIPAddress(),
            [
                'chat_id'           => $chatId,
                'telegram_username' => $telegramUsername,
                'channel_type'      => '1',
            ],
            (string) $token->getPayloadValue('jti')
        );

        return null;
    }

    private function unbindExistingChat(string $chatId): void
    {
        $owner = (new UserModel())
            ->select('id, notification_channels')
            ->where('telegram_chat_id', $chatId)
            ->where('deleted_at', null)
            ->first();

        if ($owner === null) {
            return;
        }

        $model = new UserModel();
        $model->skipValidation(true)->protect(false)
            ->update($owner['id'], [
                'telegram_chat_id'      => null,
                'telegram_username'     => null,
                'notification_channels' => $model->removeNotificationChannels($owner['notification_channels'], ['1']),
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);
    }

    private function looksLikeJwt(string $value): bool
    {
        return substr_count($value, '.') === 2;
    }
}
