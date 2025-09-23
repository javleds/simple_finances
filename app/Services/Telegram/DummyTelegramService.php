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

    public function getFile(string $fileId): array
    {
        return [
            'file_id' => $fileId,
            'file_unique_id' => 'dummy_unique_id',
            'file_size' => 0,
            'file_path' => 'dummy/path.jpg'
        ];
    }

    public function downloadFile(string $filePath): string
    {
        return 'dummy file content';
    }

    public function getFileUrl(string $filePath): string
    {
        return "https://dummy.url/{$filePath}";
    }
}
