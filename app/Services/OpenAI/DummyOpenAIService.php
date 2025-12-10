<?php

namespace App\Services\OpenAI;

use App\Contracts\OpenAIServiceInterface;
use App\Dto\MessageActionDetectionDto;
use App\Dto\OpenAIResponseDto;
use App\Dto\TransactionExtractionDto;
use App\Enums\MessageAction;
use Illuminate\Support\Facades\Log;

class DummyOpenAIService implements OpenAIServiceInterface
{
    public function detectMessageAction(string $text): array
    {
        Log::info('DummyOpenAI: Detecting message action (dummy mode)', ['text' => $text]);

        // Lógica simple para simular detección de acciones basada en palabras clave
        $text = strtolower($text);
        $action = MessageAction::CreateTransaction; // Default
        $context = [];

        if (str_contains($text, 'balance') || str_contains($text, 'saldo') || str_contains($text, 'cuánto tengo')) {
            $action = MessageAction::QueryBalance;
            // Extraer posible nombre de cuenta
            if (preg_match('/\b(ahorros?|nómina|efectivo|tarjeta|cuenta)\b/', $text, $matches)) {
                $context['account_name'] = $matches[1];
            }
        } elseif (str_contains($text, 'movimientos') || str_contains($text, 'transacciones') || str_contains($text, 'historial')) {
            $action = MessageAction::QueryRecentTransactions;
            if (preg_match('/\b(ahorros?|nómina|efectivo|tarjeta|cuenta)\b/', $text, $matches)) {
                $context['account_name'] = $matches[1];
            }
        } elseif (str_contains($text, 'modificar') || str_contains($text, 'cambiar') || str_contains($text, 'corregir')) {
            $action = MessageAction::ModifyLastTransaction;
            $context['modification_type'] = 'general';
        } elseif (str_contains($text, 'eliminar') || str_contains($text, 'borrar') || str_contains($text, 'quitar')) {
            $action = MessageAction::DeleteLastTransaction;
            $context['reason'] = 'user_request';
        }

        $dto = new MessageActionDetectionDto(
            success: true,
            action: $action,
            context: $context,
            error: null,
            rawResponse: ['dummy' => true, 'detected_keywords' => $this->extractKeywords($text)]
        );

        return $dto->toArray();
    }

    private function extractKeywords(string $text): array
    {
        $keywords = ['balance', 'saldo', 'movimientos', 'transacciones', 'modificar', 'eliminar', 'gasté', 'deposité'];
        $found = [];

        foreach ($keywords as $keyword) {
            if (str_contains(strtolower($text), $keyword)) {
                $found[] = $keyword;
            }
        }

        return $found;
    }

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

    public function transcribeAudio(string $audioPath): array
    {
        Log::info('DummyOpenAI: Transcribing audio (dummy mode)', ['audioPath' => $audioPath]);

        // Simular transcripción con texto dummy
        $dummyTranscription = 'Gasté 150 pesos en el supermercado comprando comida';

        return [
            'success' => true,
            'text' => $dummyTranscription,
            'error' => null,
        ];
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
            'Pago de servicios',
        ];

        // Detectar palabras clave para generar datos más relevantes
        $lowerInput = strtolower($input);

        if (str_contains($lowerInput, 'gast') || str_contains($lowerInput, 'pag') || str_contains($lowerInput, 'compr')) {
            $type = 'outcome';
            $concept = $concepts[array_rand(array_slice($concepts, 0, 4))];
        } elseif (str_contains($lowerInput, 'ingres') || str_contains($lowerInput, 'deposit') || str_contains($lowerInput, 'cobr')) {
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
            'financial_goal' => null, // Dummy service no detecta metas
        ];
    }
}
