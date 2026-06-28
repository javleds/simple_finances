<?php

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::post('api/telegram-webhook', TelegramWebhookController::class)->name('telegram.webhook');
