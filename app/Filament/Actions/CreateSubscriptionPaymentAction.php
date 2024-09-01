<?php

namespace App\Filament\Actions;

use App\Enums\Action as UserAction;
use App\Enums\TransactionType;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\Subscription;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Actions\Action;

class CreateSubscriptionPaymentAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('add_payment')
            ->label('Pagar')
            ->color(Color::Emerald)
            ->icon('heroicon-o-currency-dollar')
            ->form([
                Group::make()
                    ->columns()
                    ->schema([
                        Select::make('feed_account_id')
                            ->label('Cuenta de origen')
                            ->options(fn () => Account::all()->pluck('transfer_balance_label', 'id'))
                            ->default(fn (Subscription $record) => $record->feed_account_id)
                            ->searchable()
                            ->columnSpanFull()
                            ->required(),
                        TextInput::make('amount')
                            ->label('Cantidad')
                            ->prefix('$')
                            ->default(fn (Subscription $record) => $record->amount)
                            ->numeric()
                            ->required(),
                        DatePicker::make('scheduled_at')
                            ->label('Fecha')
                            ->prefixIcon('heroicon-o-calendar')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->default(Carbon::now())
                            ->maxDate(Carbon::now())
                            ->required(),
                    ])
            ])
            ->action(function (array $data, Subscription $record) {
                $transaction = Transaction::create([
                    'amount' => $data['amount'],
                    'type' => TransactionType::Outcome,
                    'scheduled_at' => $data['scheduled_at'],
                    'concept' => sprintf('Pago de subscripción %s.', $record->name),
                    'account_id' => $data['feed_account_id'],
                ]);

                event(new TransactionSaved($transaction, UserAction::Created));

                Notification::make('saved')
                    ->success()
                    ->title('Pago creado.')
                    ->send();
            });
    }
}
