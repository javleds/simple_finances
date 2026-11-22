<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\OpenAIServiceInterface;
use App\Services\OpenAI\DummyOpenAIService;
use App\Services\OpenAI\OpenAIService;

use function config;

use Illuminate\Support\ServiceProvider;

class OpenAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OpenAIServiceInterface::class, function () {
            $apiToken = config('services.openai.api_token');

            if (empty($apiToken)) {
                return new DummyOpenAIService();
            }

            return $this->app->make(OpenAIService::class);
        });
    }

    public function boot(): void
    {
    }
}
