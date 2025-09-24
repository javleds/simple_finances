<?php

namespace App\Providers;

use App\Contracts\MessageActionDetectionServiceInterface;
use App\Contracts\MessageActionProcessorInterface;
use App\Services\Telegram\Actions\BalanceQueryActionProcessor;
use App\Services\Telegram\Actions\DeleteLastTransactionActionProcessor;
use App\Services\Telegram\Actions\ModifyLastTransactionActionProcessor;
use App\Services\Telegram\Actions\RecentTransactionsActionProcessor;
use App\Services\Telegram\MessageActionDetectionService;
use App\Services\Telegram\MessageActionProcessorFactory;
use Illuminate\Support\ServiceProvider;

class MessageActionServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar el servicio de detección de acciones
        $this->app->bind(
            MessageActionDetectionServiceInterface::class,
            MessageActionDetectionService::class
        );

        // Registrar el factory como singleton
        $this->app->singleton(MessageActionProcessorFactory::class, function ($app) {
            return new MessageActionProcessorFactory();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Obtener la instancia del factory
        $factory = $this->app->make(MessageActionProcessorFactory::class);

        // Auto-registrar todos los procesadores de acciones
        $this->registerActionProcessors($factory);
    }

    /**
     * Registra automáticamente todos los procesadores de acciones
     */
    private function registerActionProcessors(MessageActionProcessorFactory $factory): void
    {
        $processors = [
            BalanceQueryActionProcessor::class,
            RecentTransactionsActionProcessor::class,
            ModifyLastTransactionActionProcessor::class,
            DeleteLastTransactionActionProcessor::class,
        ];

        foreach ($processors as $processorClass) {
            try {
                $processor = $this->app->make($processorClass);

                if ($processor instanceof MessageActionProcessorInterface) {
                    $factory->registerProcessor($processor);
                }
            } catch (\Exception $e) {
                // Log error pero no fallar el boot
                \Illuminate\Support\Facades\Log::error("Failed to register action processor: {$processorClass}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
