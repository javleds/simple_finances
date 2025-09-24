<?php

namespace App\Services\Telegram\Helpers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MessageActionHelper
{
    public static function formatBalanceResponse(array $balanceData): string
    {
        $account = $balanceData['account'];
        $balance = $balanceData['formatted_balance'];
        
        $message = "💰 **Balance de {$account->name}**\n\n";
        
        if ($balanceData['is_credit_card']) {
            $message .= "📊 Balance actual: {$balance}\n";
            $message .= "💳 Crédito disponible: {$balanceData['formatted_available_credit']}\n";
            $message .= "💸 Total gastado: {$balanceData['formatted_spent']}\n";
            $message .= "📅 Próximo corte: {$balanceData['account']->next_cutoff_date->format('d/m/Y')}\n";
        } else {
            $message .= "💵 Saldo disponible: {$balance}\n";
        }
        
        return $message;
    }

    public static function formatTransactionHistoryResponse(Collection $transactions, Account $account): string
    {
        if ($transactions->isEmpty()) {
            return "📝 No se encontraron movimientos recientes en la cuenta **{$account->name}**.";
        }

        $message = "📋 **Últimos movimientos de {$account->name}**\n\n";
        
        $transactions->each(function (Transaction $transaction, int $index) use (&$message) {
            $number = $index + 1;
            $type = $transaction->type->getLabel();
            $typeIcon = $transaction->type->name === 'Income' ? '💰' : '💸';
            $amount = as_money($transaction->amount);
            $date = $transaction->scheduled_at->format('d/m/Y');
            $userName = $transaction->user->name;
            
            $message .= "{$number}. {$typeIcon} **{$transaction->concept}**\n";
            $message .= "   {$type}: {$amount}\n";
            $message .= "   📅 {$date} | 👤 {$userName}\n\n";
        });
        
        return rtrim($message);
    }

    public static function formatNoAccountFoundResponse(string $accountName): string
    {
        return "❌ No encontré una cuenta con el nombre **{$accountName}**.\n\n" .
               "💡 Tip: Revisa el nombre de la cuenta o usa /cuentas para ver todas tus cuentas disponibles.";
    }

    public static function formatErrorResponse(string $operation): string
    {
        return "⚠️ Ocurrió un error al {$operation}. Por favor, inténtalo de nuevo más tarde.";
    }

    public static function formatTransactionModificationResponse(Transaction $transaction, array $changes): string
    {
        $message = "✅ **Transacción modificada exitosamente**\n\n";
        $message .= "📊 **Nueva información:**\n";
        $message .= "💼 Concepto: {$transaction->concept}\n";
        $message .= "💰 Cantidad: " . as_money($transaction->amount) . "\n";
        $message .= "📝 Tipo: {$transaction->type->getLabel()}\n";
        $message .= "📅 Fecha: {$transaction->scheduled_at->format('d/m/Y')}\n";
        $message .= "🏦 Cuenta: {$transaction->account->name}\n";
        
        if (!empty($changes)) {
            $message .= "\n🔄 **Cambios realizados:**\n";
            foreach ($changes as $field => $value) {
                $message .= "• " . ucfirst($field) . ": {$value}\n";
            }
        }
        
        return $message;
    }

    public static function formatTransactionDeletionResponse(Transaction $transaction): string
    {
        return "🗑️ **Transacción eliminada exitosamente**\n\n" .
               "📊 Se eliminó: {$transaction->concept}\n" .
               "💰 Cantidad: " . as_money($transaction->amount) . "\n" .
               "🏦 Cuenta: {$transaction->account->name}\n" .
               "📅 Fecha: {$transaction->scheduled_at->format('d/m/Y')}";
    }

    public static function formatNoLastTransactionResponse(): string
    {
        return "❌ No encontré ninguna transacción reciente tuya para modificar o eliminar.";
    }
}