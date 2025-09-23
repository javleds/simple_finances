<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramVerificationService;
use Illuminate\Console\Command;

class CleanExpiredTelegramCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:telegram:clean-expired-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia códigos de verificación de Telegram expirados';

    public function __construct(
        private readonly TelegramVerificationService $verificationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Limpiando códigos de verificación expirados...');

        try {
            $deletedCount = $this->verificationService->cleanExpiredCodes();

            if ($deletedCount > 0) {
                $this->info("Se eliminaron {$deletedCount} códigos expirados.");
            } else {
                $this->info('No se encontraron códigos expirados para eliminar.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error al limpiar códigos expirados: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
