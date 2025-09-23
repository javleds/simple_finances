<?php

namespace App\Contracts;

interface TelegramServiceInterface
{
    public function setWebhookUrl(string $url): void;

    public function sendMessage(string $chatId, string $message): void;
}
