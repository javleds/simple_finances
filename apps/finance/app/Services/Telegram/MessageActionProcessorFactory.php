<?php

namespace App\Services\Telegram;

use App\Contracts\MessageActionProcessorInterface;
use App\Enums\MessageAction;
use Illuminate\Support\Collection;

class MessageActionProcessorFactory
{
    private Collection $processors;

    public function __construct()
    {
        $this->processors = collect();
    }

    public function registerProcessor(MessageActionProcessorInterface $processor): void
    {
        $actionType = $processor::getActionType();

        if (! $this->processors->has($actionType->value)) {
            $this->processors->put($actionType->value, collect());
        }

        $this->processors->get($actionType->value)->push($processor);
    }

    public function getProcessor(MessageAction $action, array $context = []): ?MessageActionProcessorInterface
    {
        $processors = $this->processors->get($action->value);

        if (! $processors || $processors->isEmpty()) {
            return null;
        }

        return $processors
            ->filter(fn (MessageActionProcessorInterface $processor) => $processor->canHandle($action, $context))
            ->sortByDesc(fn (MessageActionProcessorInterface $processor) => $processor->getPriority())
            ->first();
    }

    public function getProcessorsForAction(MessageAction $action): Collection
    {
        return $this->processors->get($action->value, collect());
    }

    public function getAllProcessors(): Collection
    {
        return $this->processors->flatten();
    }

    public function hasProcessorForAction(MessageAction $action): bool
    {
        return $this->processors->has($action->value) &&
               $this->processors->get($action->value)->isNotEmpty();
    }

    public function getRegisteredActions(): Collection
    {
        return $this->processors
            ->keys()
            ->map(fn (string $value) => MessageAction::from($value));
    }
}
