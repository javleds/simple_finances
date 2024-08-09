<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Filament\Resources\AccountResource;
use App\Models\Account;
use Carbon\Carbon;
use Closure;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('transfer')
                ->label('Nueva transferencia')
                ->color(Color::Blue)
                ->form([
                    Group::make()
                        ->columns()
                        ->schema([
                            Select::make('origin_id')
                                ->label('Origen')
                                ->options(fn () => Account::all()->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->rules([
                                    fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                        if (empty($get('destination_id')) || empty($get('origin_id'))) {
                                            return;
                                        }

                                        if ($get('destination_id') === $get('origin_id')) {
                                            $fail('Las cuentas no deben ser iguales.');
                                        }
                                    }
                                ]),
                            Select::make('destination_id')
                                ->label('Destino')
                                ->options(fn () => Account::all()->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->rules([
                                    fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                        if (empty($get('destination_id')) || empty($get('origin_id'))) {
                                            return;
                                        }

                                        if ($get('destination_id') === $get('origin_id')) {
                                            $fail('Las cuentas no deben ser iguales.');
                                        }
                                    }
                                ]),
                            TextInput::make('amount')
                                ->label('Cantidad')
                                ->prefix('$')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->rules([
                                    fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                        if (empty($get('destination_id')) || empty($get('origin_id'))) {
                                            return;
                                        }

                                        $origin = Account::find($get('origin_id'));
                                        $amount = $get('amount');

                                        if ($origin->balance < $amount) {
                                            $fail(
                                                sprintf(
                                                    'Se requieren $ %s fondos, pero la cuenta %s solo tiene $ %s',
                                                    number_format($amount, 2),
                                                    $origin->name,
                                                    number_format($origin->balance, 2),
                                                )
                                            );
                                        }
                                    }
                                ]),
                            TextInput::make('concept')
                                ->label('Concepto')
                                ->placeholder('Transferencia entre cuentas')
                                ->nullable(),
                            DatePicker::make('scheduled_at')
                                ->label('Fecha')
                                ->prefixIcon('heroicon-o-calendar')
                                ->default(Carbon::now())
                                ->required(),
                        ])
                ])
                ->action(function (array $data) {
                    $origin = Account::find($data['origin_id']);
                    $destination = Account::find($data['destination_id']);
                    $amount = $data['amount'];
                    $concept = $data['concept'] ?? sprintf('Transferencia de %s a %s', $origin->name, $destination->name);
                    $date = $data['scheduled_at'];

                    $transactions = collect();

                    $transactions->add(
                        $origin->transactions()->create([
                            'concept' => $concept,
                            'amount' => $amount,
                            'type' => TransactionType::Outcome,
                            'scheduled_at' => $date,
                        ])
                    );

                    $transactions->add(
                        $destination->transactions()->create([
                            'concept' => $concept,
                            'amount' => $amount,
                            'type' => TransactionType::Income,
                            'scheduled_at' => $date,
                        ])
                    );

                    event(new BulkTransactionSaved($transactions));

                    Notification::make()
                        ->success()
                        ->title('Transacción realizada')
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
