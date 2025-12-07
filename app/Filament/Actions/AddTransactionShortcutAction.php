<?php

namespace App\Filament\Actions;

use App\Dto\TransactionFormDto;
use App\Dto\UserPaymentDto;
use App\Enums\Action as UserAction;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\User;
use App\Services\Transaction\TransactionCreator;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                            ->live()
                            ->columnSpan(fn (Get $get) => $get('type') === TransactionType::Income ? 'full' : null),
                        ToggleButtons::make('status')
                            ->label('Estatus')
                            ->inline()
                            ->grouped()
                            ->options(TransactionStatus::class)
                            ->default(TransactionStatus::Completed)
                            ->required()
                            ->hidden(fn (Get $get) => $get('type') !== TransactionType::Income),
                        Checkbox::make('split_between_users')
                            ->label('Dividir entre usuarios de la cuenta')
                            ->default(false)
                            ->hidden(function (Get $get, Account $record) {
                                if ($get('type') !== TransactionType::Outcome) {
                                    return true;
                                }

                                return $record->users()->count() <= 1;
                            })
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, Account $record) {
                                $set('user_payments', $record->users()->withoutGlobalScopes()->get()->map(function (User $user) {
                                    return [
                                        'user_id' => $user->id,
                                        'name' => $user->name,
                                        'percentage' => $user->pivot->percentage ?? 0.0,
                                    ];
                                })->toArray());
                            }),
                        Repeater::make('user_payments')
                            ->hidden(fn (Get $get) => !$get('split_between_users'))
                            ->label('Usuarios')
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        if (!$get('split_between_users')) {
                                            return;
                                        }

                                        $totalPercentage = (float) collect($value)->sum('percentage');

                                        if ($totalPercentage !== 100.0) {
                                            $fail('La suma de los porcentajes debe ser igual a 100.00 %.');
                                        }
                                    };
                                },
                            ])
                            ->schema([
                                TextInput::make('user_id')
                                    ->label('ID')
                                    ->numeric()
                                    ->required()
                                    ->readOnly(),
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(255)
                                    ->readOnly(),
                                TextInput::make('percentage')
                                    ->label('Porcentaje')
                                    ->suffix('%')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->deletable(false)
                            ->reorderable(false)
                            ->maxItems(function (Get $get, Account $record) {
                                if (!$record) {
                                    return 0;
                                }

                                return $record->users()->count();
                            })
                            ->minItems(function (Get $get, Account $record) {
                                if (!$record) {
                                    return 0;
                                }

                                return $record->users()->count();
                            }),
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
                app(TransactionCreator::class)->execute(new TransactionFormDto(
                    id: null,
                    type: $data['type'],
                    status: $data['status'] ?? TransactionStatus::Completed,
                    concept: $data['concept'],
                    amount: $data['amount'],
                    accountId: $record->id,
                    splitBetweenUsers: $data['split_between_users'] ?? false,
                    userPayments: collect($data['user_payments'] ?? [])->map(fn (array $userPayment) => UserPaymentDto::fromFormArray($userPayment))->all() ?? [],
                    scheduledAt: $data['scheduled_at'],
                    finanialGoalId: null,
                ));

                Notification::make('transaction_added')
                    ->success()
                    ->title('Transacción creada')
                    ->send();
            });
    }
}
