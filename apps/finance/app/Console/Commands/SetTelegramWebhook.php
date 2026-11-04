<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramService;
use Exception;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    public function handle(TelegramService $telegramService): int
    {
        $url = $this->argument('url');

        try {
            $statusCode = $telegramService->setWebhookUrlWithStatus($url);

            $this->info("Webhook configurado exitosamente. URL: {$url}");
            $this->info("Código de respuesta: {$statusCode}");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error al configurar el webhook: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected $signature = 'app:telegram:set-webhook {url : The webhook URL to set}';

    protected $description = 'Configura la URL del webhook para el bot de Telegram';
}
