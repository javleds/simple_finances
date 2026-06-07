<?php

namespace App\Providers;

use App\Contracts\TelegramServiceInterface;
use App\Services\Telegram\DummyTelegramService;
use App\Services\Telegram\TelegramService;
use App\Support\SpaUrl;
use Filament\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Table;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TelegramServiceInterface::class, function ($app) {
            $botToken = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');

            if (empty($botToken)) {
                return new DummyTelegramService;
            }

            return new TelegramService($botToken);
        });

        $this->app->bind(TelegramService::class, function ($app) {
            $botToken = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');

            if (empty($botToken)) {
                throw new \Exception('Telegram bot token is required for TelegramService');
            }

            return new TelegramService($botToken);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $defaultCurrency = 'MXN';
        $defaultLocale = config('app.locale');

        RateLimiter::for('auth-email-action', function (Request $request): Limit {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute(3)->by($request->ip().'|'.$email);
        });

        ResetPassword::createUrlUsing(function (object $user, string $token): string {
            return app(SpaUrl::class)->to('password-reset/reset', [
                'token' => $token,
                'email' => $user->email,
            ]);
        });

        VerifyEmail::createUrlUsing(function (object $user): string {
            return URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                    'redirect_to_spa' => 1,
                ],
            );
        });

        Model::unguard();
        Table::$defaultCurrency = $defaultCurrency;
        Table::$defaultNumberLocale = $defaultLocale;
        Infolist::$defaultNumberLocale = $defaultCurrency;
        Infolist::$defaultNumberLocale = $defaultLocale;

        Action::configureUsing(function (Action $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });

        TableAction::configureUsing(function (TableAction $action) {
            $action->modalFooterActionsAlignment(Alignment::Right);
        });

        Page::$formActionsAlignment = Alignment::Right;
    }
}
