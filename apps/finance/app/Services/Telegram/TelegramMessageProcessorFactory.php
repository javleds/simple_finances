<?php

namespace App\Services\Telegram;

use App\Contracts\TelegramMessageProcessorInterface;
use Illuminate\Support\Collection;

class TelegramMessageProcessorFactory
{
    private Collection $processors;

    public function __construct()
    {
        $this->processors = collect();
    }

    public function registerProcessor(TelegramMessageProcessorInterface $processor): void
    {
        $this->processors->put($processor::getMessageType(), $processor);
    }

    public function getProcessor(array $telegramUpdate): ?TelegramMessageProcessorInterface
    {
        return $this->processors
            ->filter(fn (TelegramMessageProcessorInterface $processor) => $processor->canHandle($telegramUpdate))
            ->sortByDesc(fn (TelegramMessageProcessorInterface $processor) => $processor->getPriority())
            ->first();
    }

    public function getAllProcessors(): Collection
    {
        return $this->processors;
    }

    public function hasProcessorForType(string $messageType): bool
    {
        return $this->processors->has($messageType);
    }
}
