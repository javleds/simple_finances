<?php

declare(strict_types=1);

use App\Contracts\TelegramMessageProcessorInterface;
use App\Contracts\TelegramServiceInterface;
use App\Models\User;
use App\Services\Legal\GetLegalAcceptanceState;
use App\Services\Telegram\TelegramMessageProcessingService;
use App\Services\Telegram\TelegramMessageProcessorFactory;

it('blocks linked Telegram messages before selecting a processor when acceptance is required', function () {
    $user = User::factory()->create(['telegram_chat_id' => '12345']);
    $state = Mockery::mock(GetLegalAcceptanceState::class);
    $state->shouldReceive('execute')->once()->withArgs(static fn (User $subject): bool => $subject->is($user))
        ->andReturn(['required' => true, 'available' => true]);
    $factory = Mockery::mock(TelegramMessageProcessorFactory::class);
    $factory->shouldNotReceive('getProcessor');
    $telegram = Mockery::mock(TelegramServiceInterface::class);
    $telegram->shouldReceive('sendMessage')->once()->withArgs(
        static fn (string $chatId, string $message): bool => '12345' === $chatId
            && str_contains($message, '/auth/legal-acceptance'),
    );

    (new TelegramMessageProcessingService($factory, $telegram, $state))->processWebhookUpdate([
        'message' => ['chat' => ['id' => 12345], 'text' => 'Gasté 50 pesos'],
    ]);
});

it('processes linked Telegram messages after current acceptance', function () {
    User::factory()->create(['telegram_chat_id' => '12345']);
    $state = Mockery::mock(GetLegalAcceptanceState::class);
    $state->shouldReceive('execute')->once()->andReturn(['required' => false, 'available' => true]);
    $update = ['message' => ['chat' => ['id' => 12345], 'text' => 'Gasté 50 pesos']];
    $processor = Mockery::mock(TelegramMessageProcessorInterface::class);
    $processor->shouldReceive('process')->once()->with($update)->andReturn('Movimiento registrado');
    $factory = Mockery::mock(TelegramMessageProcessorFactory::class);
    $factory->shouldReceive('getProcessor')->once()->with($update)->andReturn($processor);
    $telegram = Mockery::mock(TelegramServiceInterface::class);
    $telegram->shouldReceive('sendMessage')->once()->with('12345', 'Movimiento registrado');

    (new TelegramMessageProcessingService($factory, $telegram, $state))->processWebhookUpdate($update);
});

it('keeps unlinked Telegram onboarding available', function () {
    $state = Mockery::mock(GetLegalAcceptanceState::class);
    $state->shouldNotReceive('execute');
    $update = ['message' => ['chat' => ['id' => 12345], 'text' => '/start']];
    $processor = Mockery::mock(TelegramMessageProcessorInterface::class);
    $processor->shouldReceive('process')->once()->with($update)->andReturn('Bienvenido');
    $factory = Mockery::mock(TelegramMessageProcessorFactory::class);
    $factory->shouldReceive('getProcessor')->once()->with($update)->andReturn($processor);
    $telegram = Mockery::mock(TelegramServiceInterface::class);
    $telegram->shouldReceive('sendMessage')->once()->with('12345', 'Bienvenido');

    (new TelegramMessageProcessingService($factory, $telegram, $state))->processWebhookUpdate($update);
});
