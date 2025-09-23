<?php

namespace App\Dto;

class OpenAIResponseDto
{
    public function __construct(
        public readonly bool $success,
        public readonly ?TransactionExtractionDto $data = null,
        public readonly ?string $error = null,
        public readonly array $rawResponse = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->success && !is_null($this->data);
    }

    public function hasError(): bool
    {
        return !$this->success || !is_null($this->error);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data?->toArray(),
            'error' => $this->error,
            'raw_response' => $this->rawResponse,
        ];
    }
}