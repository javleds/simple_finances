<?php

use App\Models\User;
use App\Notifications\WeeklySummaryNotification;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;

uses(Tests\TestCase::class);

it('renders notification actions and fallback links inside the branded email', function (): void {
    config(['app.name' => 'Fin-Si']);

    $message = (new MailMessage)
        ->greeting('Hola, Ana.')
        ->line('Tu cuenta está lista.')
        ->action('Abrir cuenta', 'https://example.com/accounts/42');

    $html = (string) $message->render();
    $text = (string) app(Markdown::class)->renderText($message->markdown, $message->data());

    expect($html)
        ->toContain('Fin-Si', 'Tus finanzas, en orden.', '#0e6474', 'Hola, Ana.', 'Abrir cuenta')
        ->toContain('https://example.com/accounts/42')
        ->not->toContain('laravel.com', 'Laravel Logo');

    expect($text)->toContain('Tu cuenta está lista.', 'https://example.com/accounts/42');
});

it('renders custom markdown notifications with the shared branding and keeps attachments', function (): void {
    $user = new User(['name' => 'Ana']);
    $attachments = ['/tmp/weekly-summary.csv'];
    $message = (new WeeklySummaryNotification($user, $attachments))->toMail($user);

    expect((string) $message->render())
        ->toContain('Tus finanzas, en orden.', '#0e6474', 'Ana', 'resumen de las transacciones');

    expect($message->attachments)->toHaveCount(1);
    expect($message->attachments[0]['file'])->toBe($attachments[0]);
});
