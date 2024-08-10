<?php

namespace App\Filament\Actions;

use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Models\Account;
use App\Services\TransferCreator;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Actions\Action;

class DirectReceiveTransferAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('transfer_receive')
            ->label('')
            ->icon('heroicon-o-arrow-down-on-square')
            ->color(Color::Teal)
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
                            ])
                            ->disabled()
                            ->default(fn (Account $record) => $record->id),
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
            ->action(function (array $data, Account $record, TransferCreator $transferCreator) {
                $origin = Account::find($data['origin_id']);
                $destination = $record;

                $transferCreator->handle($origin, $destination, $data);
            });
    }
}