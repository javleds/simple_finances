<?php

namespace App\Services\Transaction;

use App\Contracts\OpenAIServiceInterface;
use App\Dto\TransactionExtractionDto;
use App\Enums\Action;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransactionProcessorService
{
    public function __construct(
        private readonly OpenAIServiceInterface $openAIService,
        private readonly TransactionDataValidator $validator,
        private readonly ProcessTransactionSideEffects $processTransactionSideEffects,
    ) {}

    public function processText(string $text, User $user): string
    {
        try {
            $response = $this->openAIService->processText($text);

            if (! $response['success']) {
                Log::error('OpenAI text processing failed', ['error' => $response['error']]);

                return 'Lo siento, no pude procesar tu mensaje. Inténtalo de nuevo más tarde.';
            }

            return $this->processTransactionData($response['data'], $user);
        } catch (\Exception $e) {
            Log::error('Transaction text processing failed', ['error' => $e->getMessage()]);

            return 'Ocurrió un error al procesar tu mensaje. Por favor, inténtalo de nuevo.';
        }
    }

    public function processImage(string $imagePath, string $caption, User $user): string
    {
        try {
            $response = $this->openAIService->processImage($imagePath, $caption);

            if (! $response['success']) {
                Log::error('OpenAI image processing failed', ['error' => $response['error']]);

                return 'Lo siento, no pude procesar la imagen. Inténtalo de nuevo más tarde.';
            }

            return $this->processTransactionData($response['data'], $user);
        } catch (\Exception $e) {
            Log::error('Transaction image processing failed', ['error' => $e->getMessage()]);

            return 'Ocurrió un error al procesar la imagen. Por favor, inténtalo de nuevo.';
        }
    }

    private function processTransactionData(?array $data, User $user): string
    {
        if (! $data) {
            return 'No pude extraer información de transacción de tu mensaje. Asegúrate de incluir al menos: cuenta, monto y tipo de transacción.';
        }

        $dto = new TransactionExtractionDto(
            account: $data['account'],
            amount: $data['amount'],
            type: $data['type'],
            concept: $data['concept'],
            date: $data['date'],
            financialGoal: $data['financial_goal']
        );

        if (! $dto->isValid()) {
            $missing = $dto->getMissingFields();
            $missingText = implode(', ', $missing);

            return "No pude procesar la transacción porque faltan los siguientes campos obligatorios: {$missingText}. Por favor, proporciona esta información e inténtalo de nuevo.";
        }

        $validationResult = $this->validator->validateTransactionData($dto, $user);

        if (! $validationResult['valid']) {
            return $validationResult['error'];
        }

        try {
            $transaction = $this->createTransaction($dto, $validationResult['account'], $validationResult['financial_goal'], $user);

            return $this->buildSuccessMessage($transaction, $dto);
        } catch (\Exception $e) {
            Log::error('Transaction creation failed', ['error' => $e->getMessage(), 'dto' => $dto->toArray()]);

            return 'Ocurrió un error al crear la transacción. Por favor, inténtalo de nuevo.';
        }
    }

    private function createTransaction(
        TransactionExtractionDto $dto,
        Account $account,
        ?FinancialGoal $financialGoal,
        User $user
    ): Transaction {
        $scheduledAt = $dto->date ? Carbon::parse($dto->date) : now();

        $transaction = new Transaction;
        $transaction->user_id = $user->id;
        $transaction->account_id = $account->id;
        $transaction->type = TransactionType::from($dto->type);
        $transaction->status = TransactionStatus::Completed;
        $transaction->amount = $dto->amount;
        $transaction->scheduled_at = $scheduledAt;
        $transaction->concept = $dto->concept;

        if ($financialGoal) {
            $transaction->financial_goal_id = $financialGoal->id;
        }

        $transaction->save();

        $this->processTransactionSideEffects->execute($transaction, Action::Created);

        Log::info('Transaction created successfully', [
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'account' => $account->name,
            'amount' => $dto->amount,
            'type' => $dto->type,
            'concept' => $dto->concept,
        ]);

        return $transaction;
    }

    private function buildSuccessMessage(Transaction $transaction, TransactionExtractionDto $dto): string
    {
        $typeText = $transaction->type === TransactionType::Income ? 'ingreso' : 'gasto';
        $amount = as_money($transaction->amount);
        $account = $transaction->account->name;
        $date = $transaction->scheduled_at->format('d/m/Y');

        $message = "✅ ¡Transacción creada exitosamente!\n\n";
        $message .= '📊 Tipo: '.ucfirst($typeText)."\n";
        $message .= '💰 Monto: '.$amount."\n";
        $message .= '🏦 Cuenta: '.$account."\n";
        $message .= '📋 Concepto: '.$transaction->concept."\n";
        $message .= '📅 Fecha: '.$date."\n";

        if ($transaction->financialGoal) {
            $message .= '🎯 Meta financiera: '.$transaction->financialGoal->name."\n";
        }

        return $message;
    }
}
