<?php

namespace App\Libraries\Comms\Drivers;

use App\Libraries\Comms\Exceptions\DeliveryFailedException;
use Throwable;

class TelegramDriver implements DriverInterface
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
     *
     * @param string|array $to      Chat ID de Telegram o array de chat IDs
     * @param string       $title   Título o encabezado
     * @param string|array $message Texto del mensaje o array estructurado
     * @param array        $options Opciones adicionales (inline_keyboard, cta_url, cta_label, parse_mode)
     *
     * @throws DeliveryFailedException
     */
    public function send(string|array $to, string $title, string|array $message, array $options = []): bool
    {
        if (empty($this->botToken)) {
            throw new DeliveryFailedException('telegram', 'telegram.bot_token no está configurado en el entorno.');
        }

        $chatIds = array_values(array_filter(array_map('trim', (array) $to)));
        if (empty($chatIds)) {
            throw new DeliveryFailedException('telegram', 'chat_id de destino no proporcionado o vacío.');
        }

        $text = '';
        $inlineKeyboard = null;

        if (is_array($message)) {
            $text = trim((string) ($message['text'] ?? ($message['message'] ?? ($message['body'] ?? ''))));
            $inlineKeyboard = $message['inline_keyboard'] ?? null;
        } else {
            $text = trim($message);
        }

        if (!empty($title) && !str_contains($text, $title)) {
            $text = "*{$title}*\n\n" . $text;
        }

        if (empty($text)) {
            throw new DeliveryFailedException('telegram', 'El texto del mensaje para Telegram no puede estar vacío.');
        }

        $parseMode = $options['parse_mode'] ?? 'Markdown';
        $payload = [
            'text'                     => $text,
            'parse_mode'               => $parseMode,
            'disable_web_page_preview' => true,
        ];

        if (!empty($inlineKeyboard) && is_array($inlineKeyboard)) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (!empty($options['cta_url']) || !empty($options['action_url'])) {
            $ctaUrl = $options['cta_url'] ?? $options['action_url'];
            $ctaLabel = $options['cta_label'] ?? 'Abrir enlace';
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => [[
                    ['text' => $ctaLabel, 'url' => $ctaUrl],
                ]],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $client = service('curlrequest');
        $errors = [];
        $sentCount = 0;

        foreach ($chatIds as $chatId) {
            try {
                $itemPayload = array_merge($payload, ['chat_id' => $chatId]);
                $response = $client->post(
                    $this->apiBaseUrl . '/sendMessage',
                    [
                        'json'        => $itemPayload,
                        'timeout'     => 10,
                        'http_errors' => false,
                    ]
                );

                $data = json_decode((string) $response->getBody(), true);
                if (!($data['ok'] ?? false)) {
                    $err = $data['description'] ?? 'Telegram sendMessage falló';
                    $errors[] = "[Chat: {$chatId}]: {$err}";
                    log_message('error', "[TelegramDriver] API error: {$err}");
                } else {
                    $sentCount++;
                }
            } catch (Throwable $e) {
                $errors[] = "[Chat: {$chatId}]: " . $e->getMessage();
                log_message('error', "[TelegramDriver] Exception: " . $e->getMessage());
            }
        }

        if ($sentCount === 0) {
            throw new DeliveryFailedException('telegram', 'Fallo al entregar en Telegram (' . implode('; ', $errors) . ')');
        }

        return true;
    }
}
