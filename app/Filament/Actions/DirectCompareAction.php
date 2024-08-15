<?php

namespace App\Filament\Actions;

use App\Models\Account;
use Closure;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Colors\Color;
use Filament\Tables\Actions\Action;

class DirectCompareAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('compare')
            ->label('')
            ->icon('heroicon-o-code-bracket-square')
            ->color(Color::Slate)
            ->fillForm(function (array $data, Account $record) {
                $data['origin_id'] = $record->id;

                if (!$record->feed_account_id) {
                    return $data;
                }

                $origin = $record;
                $destination = $record->feedAccount;

                $originBalance = $origin->balance;
                $destinationBalance = $destination->balance;

                $data['destination_id'] = $destination->id;
                $data['origin_balance'] = $originBalance;
                $data['destination_balance'] = $destinationBalance;
                $data['difference'] = round(abs(abs($originBalance) - abs($destinationBalance)), 2);

                return $data;
            })
            ->form([
                Group::make()
                    ->columns()
                    ->schema([
                        Select::make('origin_id')
                            ->label('Origen')
                            ->options(fn () => Account::all()->pluck('name', 'id'))
                            ->default(fn (Account $record) => $record->id)
                            ->required()
                            ->disabled(),
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

                                $originBalance = $origin->balance;
                                $destinationBalance = $destination->balance;

                                $set('origin_balance', $originBalance);
                                $set('destination_balance', $destinationBalance);
                                $set('difference', round(abs(abs($originBalance) - abs($destinationBalance)), 2));
                            }),
                        TextInput::make('origin_balance')
                            ->label('Balance en cuenta origen')
                            ->prefix('$')
                            ->numeric()
                            ->required()
                            ->readOnly()
                            ->disabled(),
                        TextInput::make('destination_balance')
                            ->label('Balance en cuenta destino')
                            ->prefix('$')
                            ->numeric()
                            ->readOnly()
                            ->disabled(),
                        TextInput::make('difference')
                            ->label('Diferencia')
                            ->prefix('$')
                            ->numeric()
                            ->columnSpanFull()
                            ->readOnly()
                            ->disabled(),
                    ])
            ])
            ->action(function (array $data) {});
    }
}
