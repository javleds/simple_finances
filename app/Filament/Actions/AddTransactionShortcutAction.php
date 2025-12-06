<?php

namespace App\Filament\Actions;

use App\Enums\Action as UserAction;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionSaved;
use App\Models\Account;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Actions\Action;

class AddTransactionShortcutAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('')
            ->modalHeading(fn (Account $record) => sprintf('Transacción en %s', $record->name))
            ->name('add_transaction')
            ->color(Color::Blue)
            ->icon('heroicon-o-banknotes')
            ->form([
                Group::make()
                    ->columns()
                    ->schema([
                        TextInput::make('concept')
                            ->label('Concepto')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('amount')
                            ->label('Cantidad')
                            ->prefix('$')
                            ->required()
                            ->numeric(),
                        ToggleButtons::make('type')
                            ->label('Tipo')
                            ->inline()
                            ->grouped()
                            ->options(TransactionType::class)
                            ->default(TransactionType::Outcome)
                            ->required()
                            ->live(),
                        ToggleButtons::make('status')
                            ->label('Estatus')
                            ->inline()
                            ->grouped()
                            ->options(TransactionStatus::class)
                            ->default(TransactionStatus::Completed)
                            ->required()
                            ->hidden(fn (Get $get) => $get('type') !== TransactionType::Income),
                        DatePicker::make('scheduled_at')
                            ->label('Fecha')
                            ->prefixIcon('heroicon-o-calendar')
                            ->default(Carbon::now())
                            ->native(false)
                            ->closeOnDateSelection()
                            ->required(),
                    ]),
            ])
            ->action(function (array $data, Account $record) {
                $transaction = $record->transactions()->create([
                    'concept' => $data['concept'],
                    'amount' => $data['amount'],
                    'type' => $data['type'],
                    'status' => $data['status'] ?? TransactionStatus::Completed,
                    'scheduled_at' => $data['scheduled_at'],
                ]);

                event(new TransactionSaved($transaction, UserAction::Created));

                Notification::make('transaction_added')
                    ->success()
                    ->title('Transacción creada')
                    ->send();
            });
    }
}
