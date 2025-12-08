<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Models\Account;
use App\Models\User;
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
                Account::query()
                    ->whereHas('transactions', function ($query) {
                        $query->where('status', 'pending')
                            ->where('user_id', auth()->id());
                    })
                    ->withSum(['transactions as pending_payment' => function ($query) {
                        $query->where('status', 'pending')
                            ->where('user_id', auth()->id());
                    }], 'amount')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cuenta'),
                Tables\Columns\TextColumn::make('pending_payment')
                    ->label('Por pagar')
                    ->formatStateUsing(fn ($state) => as_money($state))
                    ->alignRight(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_transactions')
                    ->label('Ver transacciones')
                    ->url(fn (Account $record) => TransactionResource::getUrl('index', [
                        'filters' => [
                            'account_id' => $record->id,
                            'status' => 'pending',
                        ],
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Action::make('view_all_pending')
                    ->label('Ver transacciones')
                    ->url(TransactionResource::getUrl())
            ]);
    }
}
