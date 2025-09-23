<?php

use App\Filament\Pages\PrivacyPolicy;
use App\Filament\Pages\TermsAndConditions;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('terms-and-conditions', TermsAndConditions::class)->name('terms_and_conditions');
Route::get('privacy-policy', PrivacyPolicy::class)->name('privacy_policy');

Route::post('api/telegram-webhook', TelegramWebhookController::class)->name('telegram.webhook');
