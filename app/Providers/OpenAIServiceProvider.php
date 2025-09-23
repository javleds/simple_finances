<?php

namespace App\Providers;

use App\Contracts\OpenAIServiceInterface;
use App\Services\OpenAI\DummyOpenAIService;
use App\Services\OpenAI\OpenAIService;
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
            
            return new OpenAIService();
        });
    }

    public function boot(): void
    {
        //
    }
}