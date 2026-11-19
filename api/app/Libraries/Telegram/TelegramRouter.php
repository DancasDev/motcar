<?php

namespace App\Libraries\Telegram;

use App\Libraries\Telegram\Actions\TelegramActionInterface;
use App\Libraries\Telegram\Actions\StartAction;
use App\Libraries\Telegram\Actions\BindAction;
use App\Libraries\Telegram\Actions\UnbindAction;
use App\Libraries\Telegram\Actions\StatusAction;
use App\Libraries\Telegram\Actions\HelpAction;

/**
 * Enrutador y despachador central de comandos del bot de Telegram.
 */
class TelegramRouter
{
    private TelegramBotService $bot;

    /**
     * Registro de comandos y sus clases de acción.
     * Facilita agregar nuevos comandos (ej: /sync, /reporte) sin tocar la lógica de vinculación.
     */
    private array $actions = [
        '/start'       => StartAction::class,
        '/vincular'    => BindAction::class,
        '/desvincular' => UnbindAction::class,
        '/unlink'      => UnbindAction::class,
        '/estado'      => StatusAction::class,
        '/status'      => StatusAction::class,
        '/ayuda'       => HelpAction::class,
        '/help'        => HelpAction::class,
    ];

    public function __construct(?TelegramBotService $bot = null)
    {
        $this->bot = $bot ?? new TelegramBotService();
    }

    /**
     * Registra una nueva acción de comando en tiempo de ejecución.
     */
    public function registerAction(string $command, string $actionClass): self
    {
        $this->actions[strtolower($command)] = $actionClass;
        return $this;
    }

    /**
     * Procesa un webhook update completo de Telegram.
     */
    public function handleUpdate(array $update): void
    {
        $ctx = new TelegramContext($update);

        // Solo procesamos chats privados para seguridad y vinculación de usuarios individuales
        if (!$ctx->isPrivate() || empty($ctx->getChatId())) {
            return;
        }

        $text = $ctx->getText();

        // 1. Si el mensaje contiene un token JWT suelto o en formato texto
        if ($text !== null && substr_count($text, '.') === 2 && !str_starts_with($text, '/')) {
            $this->dispatch(BindAction::class, $ctx);
            return;
        }

        // 2. Si el mensaje es un comando (/comando args...)
        if ($text !== null && str_starts_with($text, '/')) {
            $parts = explode(' ', $text, 2);
            $command = strtolower($parts[0]);

            // Remover menciones como /start@bot_name
            if (str_contains($command, '@')) {
                $command = explode('@', $command)[0];
            }

            if (isset($this->actions[$command])) {
                $this->dispatch($this->actions[$command], $ctx);
                return;
            }
        }

        // 3. Mensaje no reconocido -> enviar ayuda
        $this->dispatch(HelpAction::class, $ctx);
    }

    private function dispatch(string $actionClass, TelegramContext $ctx): void
    {
        if (!class_exists($actionClass)) {
            log_message('error', "TelegramRouter: Action class {$actionClass} not found.");
            return;
        }

        $action = new $actionClass();
        if (!($action instanceof TelegramActionInterface)) {
            log_message('error', "TelegramRouter: {$actionClass} must implement TelegramActionInterface.");
            return;
        }

        try {
            $action->handle($ctx, $this->bot);
        } catch (\Throwable $e) {
            log_message('error', "TelegramRouter dispatch error in {$actionClass}: " . $e->getMessage());
            $this->bot->sendMessage(
                $ctx->getChatId(),
                lang('Telegram.error_unexpected', [], $ctx->getLocale())
            );
        }
    }
}
