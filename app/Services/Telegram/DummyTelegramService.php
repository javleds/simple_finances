<?php

namespace App\Services\Telegram;

use App\Contracts\TelegramServiceInterface;

class DummyTelegramService implements TelegramServiceInterface
{
    public function setWebhookUrl(string $url): void
    {
    }

    public function sendMessage(string $chatId, string $message): void
    {
    }
}
