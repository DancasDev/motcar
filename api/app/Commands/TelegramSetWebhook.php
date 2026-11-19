<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\Telegram\TelegramBotService;

/**
 * Registra o actualiza el webhook del bot en la API de Telegram.
 *
 * Uso:
 *   php spark telegram:set-webhook
 *   php spark telegram:set-webhook --url https://api.midominio.com/v1/telegram/webhook
 */
class TelegramSetWebhook extends BaseCommand
{
    protected $group       = 'TELEGRAM';
    protected $name        = 'telegram:set-webhook';
    protected $description = 'Registra el webhook de Telegram usando las credenciales configuradas.';
    protected $usage       = 'telegram:set-webhook [options]';
    protected $options     = [
        '--url'          => 'URL pública HTTPS del webhook (por defecto: baseURL + v1/telegram/webhook).',
        '--keep-pending' => 'Conserva actualizaciones pendientes en Telegram en lugar de descartarlas.',
    ];

    public function run(array $params)
    {
        $config = config('Telegram');
        $token  = trim($config->botToken);
        $secret = trim($config->webhookSecret);

        if ($token === '') {
            CLI::error('Error: telegram.bot_token no está configurado en .env o Config\Telegram.');
            return;
        }

        if ($secret === '') {
            CLI::error('Error: telegram.webhook_secret no está configurado en .env o Config\Telegram.');
            return;
        }

        $url = CLI::getOption('url');
        if (!is_string($url) || trim($url) === '') {
            $base = rtrim(config('App')->baseURL, '/');
            $url  = $base . '/v1/telegram/webhook';
        }

        if (!str_starts_with(strtolower($url), 'https://')) {
            CLI::error("Error: Telegram requiere que la URL del webhook use protocolo HTTPS. URL actual: {$url}");
            return;
        }

        $dropPending = CLI::getOption('keep-pending') === null;

        CLI::write("Configurando Webhook en Telegram...", 'yellow');
        CLI::write("  URL:    {$url}");
        CLI::write("  Secret: " . (strlen($secret) > 8 ? substr($secret, 0, 4) . '***' . substr($secret, -4) : '***'));
        CLI::write("  Drop pending updates: " . ($dropPending ? 'Sí' : 'No'));

        $botService = new TelegramBotService($token);
        $result = $botService->setWebhook($url, $secret, $dropPending);

        if ($result['ok']) {
            CLI::write("✓ " . $result['description'], 'green');
        } else {
            CLI::error("✗ Error al registrar webhook: " . $result['description']);
        }
    }
}
