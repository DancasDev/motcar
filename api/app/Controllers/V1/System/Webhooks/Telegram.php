<?php

namespace App\Controllers\V1\System\Webhooks;

use CodeIgniter\HTTP\Response;
use App\Controllers\V1\BaseController;
use App\Libraries\Telegram\TelegramRouter;

/**
 * Webhook del chatbot de Telegram (POST /v1/system/webhooks/telegram).
 */
class Telegram extends BaseController
{
    public function webhook(): Response
    {
        $secret = config('Telegram')->webhookSecret;
        $header = $this->request->getHeaderLine('X-Telegram-Bot-Api-Secret-Token');
        if (empty($secret) || !hash_equals((string) $secret, $header)) {
            return $this->fail(lang('Telegram.webhook_unauthorized'), 401, 'TG_WEBHOOK_UNAUTHORIZED');
        }

        try {
            $update = $this->request->getJSON(true);
        } catch (\Throwable) {
            $update = null;
        }
        if (!is_array($update)) {
            return $this->respond(['status' => 200, 'data' => []], 200);
        }

        try {
            (new TelegramRouter())->handleUpdate($update);
        } catch (\Throwable $e) {
            log_message('error', '[TelegramWebhook] ' . $e->getMessage());
        }

        return $this->respond(['status' => 200, 'data' => []], 200);
    }
}
