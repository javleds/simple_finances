<?php

namespace App\Filament\Actions;

use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Models\Account;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;

class CompareAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('compare')
            ->label('Comparar cuentas')
            ->color(Color::Emerald)
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
                            ])
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                if (empty($get('origin_id')) || empty($get('destination_id'))) {
                                    return;
                                }

                                $origin = Account::find($get('origin_id'));
                                $destination = Account::find($get('destination_id'));

                                $originBalance = $origin->credit_card ? $origin->scoped_balance : $origin->balance;
                                $destinationBalance = $destination->credit_card ? $destination->scoped_balance : $destination->balance;

                                $set('origin_balance', $originBalance);
                                $set('destination_balance', $destinationBalance);
                                $set('difference', abs(abs($originBalance) - abs($destinationBalance)));
                            }),
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
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                if (empty($get('origin_id')) || empty($get('destination_id'))) {
                                    return;
                                }

                                $origin = Account::find($get('origin_id'));
                                $destination = Account::find($get('destination_id'));

                                $originBalance = $origin->credit_card ? $origin->scoped_balance : $origin->balance;
                                $destinationBalance = $destination->credit_card ? $destination->scoped_balance : $destination->balance;

                                $set('origin_balance', $originBalance);
                                $set('destination_balance', $destinationBalance);
                                $set('difference', abs(abs($originBalance) - abs($destinationBalance)));
                            }),
                        TextInput::make('origin_balance')
                            ->label('Balance en cuenta origen')
                            ->prefix('$')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->readOnly(),
                        TextInput::make('destination_balance')
                            ->label('Balance en cuenta destino')
                            ->prefix('$')
                            ->numeric()
                            ->readOnly(),
                        TextInput::make('difference')
                            ->label('Diferencia')
                            ->prefix('$')
                            ->numeric()
                            ->columnSpanFull()
                            ->readOnly(),
                    ])
            ])
            ->action(function (array $data) {});
    }
}
