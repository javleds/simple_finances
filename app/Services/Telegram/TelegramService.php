<?php

namespace App\Services\Telegram;

use App\Contracts\TelegramServiceInterface;
use Illuminate\Support\Facades\Http;

class TelegramService implements TelegramServiceInterface
{
    private readonly string $baseUrl;

    public function __construct(private readonly string $botToken)
    {
        $this->baseUrl = "https://api.telegram.org/bot{$botToken}";
    }

    public function setWebhookUrl(string $url): void
    {
        $response = Http::post("{$this->baseUrl}/setWebhook", [
            'url' => $url,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to set webhook: " . $response->body());
        }
    }

    public function sendMessage(string $chatId, string $message): void
    {
        $response = Http::post("{$this->baseUrl}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to send message: " . $response->body());
        }
    }
}
