<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('api/telegram-webhook', TelegramWebhookController::class)->name('telegram.webhook');
