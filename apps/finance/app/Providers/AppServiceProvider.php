<?php

namespace App\Providers;

use App\Contracts\TelegramServiceInterface;
use App\Services\Telegram\DummyTelegramService;
use App\Services\Telegram\TelegramService;
use App\Support\SpaUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        RateLimiter::for('whatsapp-code', function (Request $request): array {
            $userKey = 'whatsapp-user:'.$request->user()->getAuthIdentifier();
            $phone = $request->input('phone_number');
            $phoneKey = 'whatsapp-phone:'.hash('sha256', is_string($phone) ? $phone : '');

            return [
                Limit::perMinute(1)->by($userKey.':minute'),
                Limit::perHour(5)->by($userKey.':hour'),
                Limit::perMinute(1)->by($phoneKey.':minute'),
                Limit::perHour(5)->by($phoneKey.':hour'),
            ];
        });

        RateLimiter::for('whatsapp-confirm', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('whatsapp-confirm:'.$request->user()->getAuthIdentifier()));

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
    }
}
