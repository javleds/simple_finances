<?php

namespace App\Services\OpenAI;

use App\Contracts\OpenAIServiceInterface;
use App\Dto\OpenAIResponseDto;
use App\Dto\TransactionExtractionDto;
use Illuminate\Support\Facades\Log;

class DummyOpenAIService implements OpenAIServiceInterface
{
    public function processText(string $text): array
    {
        Log::info('DummyOpenAI: Processing text (dummy mode)', ['text' => $text]);

        $response = new OpenAIResponseDto(
            success: true,
            data: new TransactionExtractionDto(),
            error: null,
            rawResponse: ['dummy' => true, 'mode' => 'text']
        );

        return $response->toArray();
    }

    public function processImage(string $imagePath): array
    {
        Log::info('DummyOpenAI: Processing image (dummy mode)', ['imagePath' => $imagePath]);

        $response = new OpenAIResponseDto(
            success: true,
            data: new TransactionExtractionDto(),
            error: null,
            rawResponse: ['dummy' => true, 'mode' => 'image']
        );

        return $response->toArray();
    }

    public function processAudio(string $audioPath): array
    {
        Log::info('DummyOpenAI: Processing audio (dummy mode)', ['audioPath' => $audioPath]);

        $response = new OpenAIResponseDto(
            success: true,
            data: new TransactionExtractionDto(),
            error: null,
            rawResponse: ['dummy' => true, 'mode' => 'audio']
        );

        return $response->toArray();
    }
}
