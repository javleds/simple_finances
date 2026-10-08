<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('api/telegram-webhook', TelegramWebhookController::class)->name('telegram.webhook');

Route::view('/{path?}', 'spa')
    ->where('path', '(?!(?:api|up|build|storage)(?:/|$)|.*\.[^/]+$).*')
    ->name('spa');
