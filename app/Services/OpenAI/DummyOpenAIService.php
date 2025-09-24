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

        // Generar datos de ejemplo basados en el texto
        $dummyData = $this->generateDummyData($text, 'text');

        $response = new OpenAIResponseDto(
            success: true,
            data: new TransactionExtractionDto(
                account: $dummyData['account'],
                amount: $dummyData['amount'],
                type: $dummyData['type'],
                concept: $dummyData['concept'],
                date: $dummyData['date'],
                financialGoal: $dummyData['financial_goal']
            ),
            error: null,
            rawResponse: ['dummy' => true, 'mode' => 'text']
        );

        return $response->toArray();
    }

    public function processImage(string $imagePath): array
    {
        Log::info('DummyOpenAI: Processing image (dummy mode)', ['imagePath' => $imagePath]);

        $dummyData = $this->generateDummyData('Procesando imagen', 'image');

        $response = new OpenAIResponseDto(
            success: true,
            data: new TransactionExtractionDto(
                account: $dummyData['account'],
                amount: $dummyData['amount'],
                type: $dummyData['type'],
                concept: $dummyData['concept'],
                date: $dummyData['date'],
                financialGoal: $dummyData['financial_goal']
            ),
            error: null,
            rawResponse: ['dummy' => true, 'mode' => 'image']
        );

        return $response->toArray();
    }

    public function processAudio(string $audioPath): array
    {
        Log::info('DummyOpenAI: Processing audio (dummy mode)', ['audioPath' => $audioPath]);

        $dummyData = $this->generateDummyData('Procesando audio', 'audio');

        $response = new OpenAIResponseDto(
            success: true,
            data: new TransactionExtractionDto(
                account: $dummyData['account'],
                amount: $dummyData['amount'],
                type: $dummyData['type'],
                concept: $dummyData['concept'],
                date: $dummyData['date'],
                financialGoal: $dummyData['financial_goal']
            ),
            error: null,
            rawResponse: ['dummy' => true, 'mode' => 'audio']
        );

        return $response->toArray();
    }

    private function generateDummyData(string $input, string $mode): array
    {
        // Generar datos dummy más realistas para testing
        $accounts = ['nomina', 'ahorros', 'efectivo', 'tarjeta'];
        $concepts = [
            'Compra de comida',
            'Pago de gasolina',
            'Salario mensual',
            'Compra en supermercado',
            'Transferencia bancaria',
            'Pago de servicios'
        ];

        // Detectar palabras clave para generar datos más relevantes
        $lowerInput = strtolower($input);

        if (str_contains($lowerInput, 'gast') || str_contains($lowerInput, 'pag') || str_contains($lowerInput, 'compr')) {
            $type = 'outcome';
            $concept = $concepts[array_rand(array_slice($concepts, 0, 4))];
        } else if (str_contains($lowerInput, 'ingres') || str_contains($lowerInput, 'deposit') || str_contains($lowerInput, 'cobr')) {
            $type = 'income';
            $concept = $concepts[array_rand(array_slice($concepts, 2, 4))];
        } else {
            $type = 'outcome'; // Default
            $concept = $concepts[array_rand($concepts)];
        }

        return [
            'account' => $accounts[array_rand($accounts)],
            'amount' => rand(50, 2000) + (rand(0, 99) / 100), // Random amount with decimals
            'type' => $type,
            'concept' => $concept,
            'date' => null, // Dummy service no proporciona fecha
            'financial_goal' => null // Dummy service no detecta metas
        ];
    }
}
