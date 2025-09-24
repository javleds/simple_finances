<?php

namespace App\Dto;

use App\Enums\MessageAction;

class MessageActionDetectionDto
{
    public function __construct(
        public readonly bool $success,
        public readonly ?MessageAction $action = null,
        public readonly array $context = [],
        public readonly ?string $error = null,
        public readonly array $rawResponse = []
    ) {}

    public function isSuccess(): bool
    {
        return $this->success && !is_null($this->action);
    }

    public function hasError(): bool
    {
        return !$this->success || !is_null($this->error);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'action' => $this->action?->value,
            'context' => $this->context,
            'error' => $this->error,
            'raw_response' => $this->rawResponse,
        ];
    }
}
