<?php

namespace App\Filament\Widgets;

use App\Dto\TransactionFormDto;
use App\Enums\TransactionStatus;
use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\Transaction\TransactionUpdater;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingTransactionsByAccount extends BaseWidget
{
    protected static ?string $heading = 'Transacciones pendientes por cuenta';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->where('status', TransactionStatus::Pending)
                    ->where('user_id', auth()->id())
                    ->with('account', 'subTransactions')
            )
            ->columns([
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Cuenta'),
                Tables\Columns\TextColumn::make('concept')
                    ->label('Concepto'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->formatStateUsing(fn (float $state) => as_money($state))
                    ->alignRight(),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_completed')
                    ->label('')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation('¿Estás seguro de que deseas marcar esta transacción como completada?')
                    ->visible(fn (Transaction $record) => $record->status === TransactionStatus::Pending && $record->user_id === auth()->id())
                    ->action(function (Transaction $record) {
                        $subTransactions = $record->subTransactions()->get();
                        $userPayments = $subTransactions->map(function (Transaction $sub) {
                            $percentage = $sub->percentage ?? 0.0;

                            return [
                                'user_id' => $sub->user_id,
                                'percentage' => $percentage,
                            ];
                        })->toArray();

                        app(TransactionUpdater::class)->execute($record, TransactionFormDto::fromFormArray([
                            'id' => $record->id,
                            'type' => $record->type,
                            'status' => TransactionStatus::Completed,
                            'concept' => $record->concept,
                            'amount' => $record->amount,
                            'account_id' => $record->account_id,
                            'split_between_users' => $subTransactions->isNotEmpty(),
                            'user_payments' => $userPayments,
                            'scheduled_at' => $record->scheduled_at,
                            'financial_goal_id' => $record->financial_goal_id,
                        ]));
                    }),
            ])
            ->headerActions([
                Action::make('view_all_pending')
                    ->label('Ver transacciones')
                    ->url(TransactionResource::getUrl())
            ]);
    }
}
