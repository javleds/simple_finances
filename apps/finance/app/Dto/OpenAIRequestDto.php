<?php

namespace App\Dto;

class OpenAIRequestDto
{
    public function __construct(
        public readonly string $content,
        public readonly string $type, // 'text', 'image', 'audio'
        public readonly string $model,
        public readonly array $parameters = [],
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'type' => $this->type,
            'model' => $this->model,
            'parameters' => $this->parameters,
        ];
    }
}
