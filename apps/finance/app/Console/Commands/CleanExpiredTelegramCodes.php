<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramVerificationService;
use Exception;
use Illuminate\Console\Command;

class CleanExpiredTelegramCodes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TelegramVerificationService $verificationService): int
    {
        $this->info('Limpiando códigos de verificación expirados...');

        try {
            $deletedCount = $verificationService->cleanExpiredCodes();

            if ($deletedCount > 0) {
                $this->info("Se eliminaron {$deletedCount} códigos expirados.");
            } else {
                $this->info('No se encontraron códigos expirados para eliminar.');
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error al limpiar códigos expirados: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

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
}
