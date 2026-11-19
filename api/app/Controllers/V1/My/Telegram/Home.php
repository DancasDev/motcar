<?php

namespace App\Controllers\V1\My\Telegram;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Models\Gac\UserModel;
use App\Models\System\BinnacleModel;

/**
 * Gestión de vinculación de la cuenta de Telegram del usuario autenticado.
 */
class Home extends BaseController {
    protected $modelName = 'App\Models\Gac\UserModel';

    private const PENDING_PREFIX = 'tmp_tg_sub_';
    private const BINDING_TTL = 900;

    /**
     * Genera un token efímero de vinculación y el deep-link para Telegram.
     */
    public function subscribe(): Response {
        if (!$this->isChannelActive()) {
            return $this->fail(lang('My.telegram.channel_disabled'), 403, 'MY_TELEGRAM_CHANNEL_DISABLED');
        }

        $userId = $this->authUserId();
        $user = $this->findUser($userId);

        if (!empty($user['telegram_chat_id'])) {
            return $this->fail(lang('My.telegram.already_bound'), 400, 'MY_TELEGRAM_ALREADY_BOUND');
        }

        $this->revokePending($userId);

        $telegramConfig = config('Telegram');
        $ttl = config('Jwt')->getActionTtl('telegram_bind') ?: ($telegramConfig->bindingTtlSeconds ?: self::BINDING_TTL);
        $token = service('actionToken');
        $token->setPayloadValue([
            'sub'  => $userId,
            'subt' => '1',
            'act'  => 'telegram_bind',
            'aud'  => 'notification_subscription',
        ]);
        $token->setExpirationTime($ttl);
        $jwt = $token->encode();

        cache()->save($this->pendingPrefix() . $userId, $jwt, $ttl);

        $botUsername = $telegramConfig->botUsername;
        $deepLink = $botUsername !== '' ? 'https://t.me/' . $botUsername . '?start=' . $jwt : null;

        return $this->respond([
            'status'   => 200,
            'data'     => [
                'token'        => $jwt,
                'deep_link'    => $deepLink,
                'bot_username' => $botUsername !== '' ? $botUsername : null,
                'expires_at'   => date('Y-m-d H:i:s', time() + $ttl),
                'ttl_seconds'  => $ttl,
            ],
            'messages' => [
                'success' => lang('My.telegram.link_generated'),
            ],
        ], 200);
    }

    /**
     * Consulta el estado de vinculación del usuario autenticado con Telegram.
     */
    public function status(): Response {
        if (!$this->isChannelActive()) {
            return $this->fail(lang('My.telegram.channel_disabled'), 403, 'MY_TELEGRAM_CHANNEL_DISABLED');
        }

        $userId = $this->authUserId();
        $user = $this->findUser($userId);

        if (!empty($user['telegram_chat_id'])) {
            $masked = substr((string) $user['telegram_chat_id'], 0, 3) . '***';
            return $this->respond(['status' => 200, 'data' => [
                'status'       => 'bound',
                'username'     => $user['telegram_username'] ?? null,
                'channel_key'  => $masked,
            ]], 200);
        }

        $jwt = cache()->get($this->pendingPrefix() . $userId);
        if (!is_string($jwt)) {
            return $this->respond(['status' => 200, 'data' => ['status' => 'unbound']], 200);
        }

        try {
            $token = service('actionToken');
            $token->decode($jwt);
            $exp = (int) $token->getPayloadValue('exp');
            $act = $token->getPayloadValue('act');
            if (time() > $exp || $token->isRevoked(false) || $act !== 'telegram_bind') {
                return $this->respond(['status' => 200, 'data' => ['status' => 'expired']], 200);
            }
            return $this->respond(['status' => 200, 'data' => [
                'status'     => 'pending',
                'expires_at' => date('Y-m-d H:i:s', $exp),
            ]], 200);
        } catch (\Throwable $e) {
            return $this->respond(['status' => 200, 'data' => ['status' => 'expired']], 200);
        }
    }

    /**
     * Desvincula la cuenta de Telegram del usuario autenticado.
     */
    public function unsubscribe(): Response {
        if (!$this->isChannelActive()) {
            return $this->fail(lang('My.telegram.channel_disabled'), 403, 'MY_TELEGRAM_CHANNEL_DISABLED');
        }

        $userId = $this->authUserId();
        $user = $this->findUser($userId);
        $chatId = $user['telegram_chat_id'] ?? null;

        $this->revokePending($userId);

        if (!empty($chatId)) {
            $this->model->skipValidation(true)->protect(false)
                ->update($user['id'], [
                    'telegram_chat_id'  => null,
                    'telegram_username' => null,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);

            (new BinnacleModel())->log(
                'user',
                $userId,
                'telegram',
                (string) $chatId,
                'delete',
                date('Y-m-d H:i:s'),
                $this->request->getIPAddress(),
                [
                    'chat_id'      => $chatId,
                    'channel_type' => 'telegram',
                ],
                service('authToken')->getPayloadValue('jti')
            );
        }

        return $this->respond([
            'status'   => 200,
            'data'     => [
                'id'       => $userId,
                'affected' => true,
                'status'   => 'unbound',
            ],
            'messages' => [
                'success' => lang('My.telegram.unbound_successfully'),
            ],
        ], 200);
    }

    // ---------------------------------------------------------------
    //  Funciones auxiliares
    // ---------------------------------------------------------------
    private function isChannelActive(): bool {
        return config('Comms')->isMessagingChannelEnabled('telegram') && !empty(config('Telegram')->botToken);
    }

    private function authUserId(): int {
        return (int) service('authToken')->getPayloadValue('sub');
    }

    private function findUser(int $userId): ?array {
        return $this->model
            ->select('id, telegram_chat_id, telegram_username, language_id')
            ->where('id', $userId)
            ->limit(1)
            ->get()
            ->getRowArray() ?: null;
    }

    private function revokePending(int $userId): void {
        $key = $this->pendingPrefix() . $userId;
        $jwt = cache()->get($key);
        if (is_string($jwt)) {
            try {
                $token = service('actionToken');
                $token->decode($jwt);
                $token->revoke(true);
            } catch (\Throwable $e) {
            }
            cache()->delete($key);
        }
    }

    private function pendingPrefix(): string {
        return (string) (config('Telegram')->bindingCachePrefix ?: self::PENDING_PREFIX);
    }
}
