<?php

namespace App\Libraries\Telegram;

use App\Models\Gac\UserModel;

/**
 * Objeto de Contexto que encapsula los datos de un update de Telegram.
 */
class TelegramContext
{
    private array $rawUpdate;
    private array $message;
    private string $chatId;
    private ?int $messageId;
    private string $chatType;
    private ?string $telegramUsername;
    private ?string $firstName;
    private ?string $lastName;
    private ?string $text;
    private ?array $boundUser = null;
    private bool $boundUserLoaded = false;

    public function __construct(array $update)
    {
        $this->rawUpdate = $update;
        $this->message = $update['message'] ?? ($update['edited_message'] ?? []);
        
        $chat = $this->message['chat'] ?? [];
        $this->chatId = (string) ($chat['id'] ?? '');
        $this->chatType = (string) ($chat['type'] ?? 'private');
        $this->messageId = isset($this->message['message_id']) ? (int) $this->message['message_id'] : null;
        
        $from = $this->message['from'] ?? $chat;
        $this->telegramUsername = isset($from['username']) ? (string) $from['username'] : null;
        $this->firstName = isset($from['first_name']) ? (string) $from['first_name'] : null;
        $this->lastName = isset($from['last_name']) ? (string) $from['last_name'] : null;
        
        $this->text = isset($this->message['text']) ? trim((string) $this->message['text']) : null;
    }

    public function getUpdate(): array
    {
        return $this->rawUpdate;
    }

    public function getMessage(): array
    {
        return $this->message;
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }

    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    public function getChatType(): string
    {
        return $this->chatType;
    }

    public function isPrivate(): bool
    {
        return $this->chatType === 'private';
    }

    public function getTelegramUsername(): ?string
    {
        return $this->telegramUsername;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? '')) ?: ($this->telegramUsername ?? 'Usuario');
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function hasText(): bool
    {
        return !empty($this->text);
    }

    /**
     * Obtiene el usuario de la base de datos vinculado a este chat_id, si existe.
     */
    public function getBoundUser(): ?array
    {
        if (!$this->boundUserLoaded) {
            if (!empty($this->chatId)) {
                $this->boundUser = (new UserModel())
                    ->select('id, username, first_name, last_name, email, telegram_chat_id, telegram_username, notification_channels, language_id, created_at')
                    ->where('telegram_chat_id', $this->chatId)
                    ->where('is_disabled', '0')
                    ->where('deleted_at', null)
                    ->first();
            }
            $this->boundUserLoaded = true;
        }

        return $this->boundUser;
    }

    /**
     * Resuelve el locale configurado para el usuario vinculado o el idioma por defecto.
     */
    public function getLocale(): string
    {
        $user = $this->getBoundUser();
        return (new \App\Models\Global\LanguageModel())->resolveLocale($user['language_id'] ?? null);
    }

    public function isBound(): bool
    {
        return $this->getBoundUser() !== null;
    }

    public function setBoundUser(?array $user): void
    {
        $this->boundUser = $user;
        $this->boundUserLoaded = true;
    }
}
