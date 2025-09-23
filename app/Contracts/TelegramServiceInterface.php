<?php

namespace App\Contracts;

interface TelegramServiceInterface
{
    public function setWebhookUrl(string $url): void;

    public function sendMessage(string $chatId, string $message): void;

    public function getFile(string $fileId): array;

    public function downloadFile(string $filePath): string;

    public function getFileUrl(string $filePath): string;
}
