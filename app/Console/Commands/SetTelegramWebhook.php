<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    protected $signature = 'app:telegram:set-webhook {url : The webhook URL to set}';

    protected $description = 'Configura la URL del webhook para el bot de Telegram';

    public function __construct(private readonly TelegramService $telegramService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $url = $this->argument('url');

        try {
            $statusCode = $this->telegramService->setWebhookUrlWithStatus($url);

            $this->info("Webhook configurado exitosamente. URL: {$url}");
            $this->info("Código de respuesta: {$statusCode}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error al configurar el webhook: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
