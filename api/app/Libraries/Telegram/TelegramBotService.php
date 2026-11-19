<?php

namespace App\Libraries\Telegram;

/**
 * Cliente HTTP para la API de Telegram Bot.
 */
class TelegramBotService
{
    private string $botToken;
    private string $apiBaseUrl;

    public function __construct(?string $botToken = null)
    {
        $this->botToken = $botToken ?? (string) config('Telegram')->botToken;
        $this->apiBaseUrl = 'https://api.telegram.org/bot' . $this->botToken;
    }

    /**
     * Envía un mensaje a un chat de Telegram.
     */
    public function sendMessage(string|int $chatId, string $text, array $options = []): array
    {
        if (empty($this->botToken)) {
            throw new \RuntimeException('telegram.botToken no está configurado');
        }

        $payload = array_merge([
            'chat_id'                  => (string) $chatId,
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ], $options);

        $response = service('curlrequest')->post(
            $this->apiBaseUrl . '/sendMessage',
            [
                'json'    => $payload,
                'timeout' => 10,
            ]
        );

        $data = json_decode((string) $response->getBody(), true);
        if (!($data['ok'] ?? false)) {
            log_message('error', 'Telegram sendMessage error: ' . json_encode($data));
            throw new \RuntimeException($data['description'] ?? 'sendMessage falló');
        }

        return $data;
    }

    /**
     * Elimina un mensaje del chat (por ejemplo, el mensaje con el token de vinculación).
     */
    public function deleteMessage(string|int $chatId, int $messageId): bool
    {
        if (empty($this->botToken) || empty($messageId)) {
            return false;
        }

        try {
            $response = service('curlrequest')->post(
                $this->apiBaseUrl . '/deleteMessage',
                [
                    'json'    => [
                        'chat_id'    => (string) $chatId,
                        'message_id' => $messageId,
                    ],
                    'timeout' => 5,
                ]
            );

            $data = json_decode((string) $response->getBody(), true);
            return (bool) ($data['ok'] ?? false);
        } catch (\Throwable $e) {
            log_message('info', 'Telegram deleteMessage skipped: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Configura el webhook en Telegram.
     *
     * @return array{ok: bool, description: string}
     */
    public function setWebhook(string $url, ?string $secret = null, bool $dropPending = true): array
    {
        if (empty($this->botToken)) {
            return ['ok' => false, 'description' => 'telegram.bot_token no está configurado'];
        }

        $payload = [
            'url'                  => $url,
            'drop_pending_updates' => $dropPending,
        ];
        if (!empty($secret)) {
            $payload['secret_token'] = $secret;
        }

        try {
            $response = service('curlrequest')->post(
                $this->apiBaseUrl . '/setWebhook',
                [
                    'json'        => $payload,
                    'timeout'     => 15,
                    'http_errors' => false,
                ]
            );

            $data = json_decode((string) $response->getBody(), true);
            return [
                'ok'          => (bool) ($data['ok'] ?? false),
                'description' => (string) ($data['description'] ?? (($data['ok'] ?? false) ? 'Webhook configurado exitosamente' : 'Respuesta desconocida de Telegram')),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }
}
