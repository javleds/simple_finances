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

        if (! $response->successful()) {
            throw new \Exception('Error al configurar webhook: '.$response->body());
        }
    }

    public function setWebhookUrlWithStatus(string $url): int
    {
        $response = Http::post("{$this->baseUrl}/setWebhook", [
            'url' => $url,
        ]);

        if (! $response->successful()) {
            throw new \Exception('Error al configurar webhook: '.$response->body());
        }

        return $response->status();
    }

    public function sendMessage(string $chatId, string $message): void
    {
        $response = Http::post("{$this->baseUrl}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);

        if (! $response->successful()) {
            throw new \Exception('Error al enviar mensaje: '.$response->body());
        }
    }

    public function getFile(string $fileId): array
    {
        $response = Http::get("{$this->baseUrl}/getFile", [
            'file_id' => $fileId,
        ]);

        if (! $response->successful()) {
            throw new \Exception('Error al obtener información del archivo: '.$response->body());
        }

        $data = $response->json();

        if (! $data['ok']) {
            throw new \Exception('Error en la respuesta de Telegram: '.($data['description'] ?? 'Error desconocido'));
        }

        return $data['result'];
    }

    public function downloadFile(string $filePath): string
    {
        $downloadUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";

        $response = Http::get($downloadUrl);

        if (! $response->successful()) {
            throw new \Exception('Error al descargar archivo: '.$response->body());
        }

        return $response->body();
    }

    public function getFileUrl(string $filePath): string
    {
        return "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
    }
}
