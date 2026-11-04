<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

uses(Tests\TestCase::class);

it('boots the console without configuring the optional Telegram integration', function (): void {
    $process = new Process(
        [\PHP_BINARY, base_path('artisan'), 'list', '--raw'],
        base_path(),
        ['TELEGRAM_BOT_TOKEN' => ''],
    );
    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('app:telegram:set-webhook');
});
