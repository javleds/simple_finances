<?php

namespace App\Providers;

use App\Contracts\TelegramMessageProcessorInterface;
use App\Services\Telegram\TelegramFileService;
use App\Services\Telegram\TelegramMessageProcessorFactory;
use App\Services\Telegram\TelegramMessageProcessingService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class TelegramMessageProcessorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelegramFileService::class);

        $this->app->singleton(TelegramMessageProcessorFactory::class, function ($app) {
            $factory = new TelegramMessageProcessorFactory();

            $this->registerProcessors($factory);

            return $factory;
        });

        $this->app->singleton(TelegramMessageProcessingService::class);
    }

    private function registerProcessors(TelegramMessageProcessorFactory $factory): void
    {
        $processorsPath = app_path('Services/Telegram/Processors');

        if (!File::exists($processorsPath)) {
            return;
        }

        $files = File::files($processorsPath);

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);

            if ($this->isValidProcessor($className)) {
                $processor = $this->app->make($className);
                $factory->registerProcessor($processor);
            }
        }
    }

    private function getClassNameFromFile(\SplFileInfo $file): string
    {
        $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        return "App\\Services\\Telegram\\Processors\\{$filename}";
    }

    private function isValidProcessor(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);

        return $reflection->implementsInterface(TelegramMessageProcessorInterface::class)
            && !$reflection->isAbstract();
    }

    public function boot(): void
    {
        //
    }
}
