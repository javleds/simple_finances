<?php

namespace App\Filament\Resources\LoanResource\RelationManagers;

use App\Enums\Frequency;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Services\BasedOnNumberOfPaymentsPaymentGenerator;
use App\Services\RelativeDaysPaymentGenerator;
use Carbon\Carbon;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use stdClass;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'Esquema de pagos';
    protected static ?string $label = 'Esquema';
    protected static ?string $pluralLabel = 'Esquema de pagos';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
//                Tables\Columns\TextColumn::make('id')
//                    ->label('No. de pago')
//                    ->formatStateUsing(fn (stdClass $rowLoop) => $rowLoop->index + 1)
//                    ->sortable()
//                    ->searchable(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Fecha de pago')
                    ->date()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Cantidad')
                    ->formatStateUsing(fn ($state) => as_money($state))
                    ->alignRight()
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_schema')
                    ->requiresConfirmation()
                    ->modalWidth(MaxWidth::MaxContent)
                    ->form([
                        Forms\Components\Group::make()
                            ->columns(1)
                            ->schema([
                                Forms\Components\Wizard::make([
                                    Forms\Components\Wizard\Step::make('Tipo de esquema')
                                        ->schema([
                                            Forms\Components\Select::make('type')
                                                ->label('Base')
                                                ->options([
                                                    'payments' => 'Número de pagos',
                                                    'frequency' => 'Por frecuencia de pago',
                                                    'custom' => 'Personalizado',
                                                ])
                                                ->searchable()
                                                ->required()
                                                ->live()
                                        ]),
                                    Forms\Components\Wizard\Step::make('Datos del esquema')
                                        ->hidden(fn (Forms\Get $get) => $get('type') === 'custom')
                                        ->schema([
                                            // Shared
                                            Forms\Components\DatePicker::make('first_payment_date')
                                                ->label('Fecha de primer pago')
                                                ->native(false)
                                                ->closeOnDateSelection()
                                                ->default(fn () => $this->getOwnerRecord()->done_at)
                                                ->required(fn (Forms\Get $get) => $get('type') !== 'custom'),
                                            // Payments type
                                            Forms\Components\TextInput::make('number_of_payments')
                                                ->label('No. de pagos')
                                                ->numeric()
                                                ->hidden(fn (Forms\Get $get) => $get('type') !== 'payments')
                                                ->required(fn (Forms\Get $get) => $get('type') === 'payments'),
                                            Forms\Components\Section::make('Frecuencia')
                                                ->hidden(fn (Forms\Get $get) => $get('type') !== 'payments')
                                                ->columns()
                                                ->schema([
                                                    Forms\Components\TextInput::make('frequency_unit')
                                                        ->label('Cada')
                                                        ->numeric()
                                                        ->default(1)
                                                        ->required(fn (Forms\Get $get) => $get('type') === 'payments'),
                                                    Forms\Components\Select::make('frequency_type')
                                                        ->label('Unidad')
                                                        ->options(Frequency::class)
                                                        ->default(Frequency::Month)
                                                        ->searchable()
                                                        ->required(fn (Forms\Get $get) => $get('type') === 'payments'),
                                                ]),
                                            // Frequency type
                                            Forms\Components\Repeater::make('every_of_month')
                                                ->label('Día de mes')
                                                ->columns()
                                                ->schema([
                                                    Forms\Components\TextInput::make('day')
                                                        ->label('Día')
                                                        ->required(),
                                                    Forms\Components\TextInput::make('amount')
                                                        ->label('Cantidad')
                                                        ->prefix('$')
                                                        ->numeric()
                                                        ->minValue(0.1)
                                                        ->required(),
                                                ])
                                                ->hint('Si agrega valores 5 y 20, se crearan 2 pagos por mes, los días 5 y 20.')
                                                ->maxItems(31)
                                                ->hidden(fn (Forms\Get $get) => $get('type') !== 'frequency')
                                                ->required(fn (Forms\Get $get) => $get('type') === 'frequency'),
                                        ])
                                        ->afterStateUpdated(
                                            function (
                                                Forms\Get $get,
                                                Forms\Set $set,
                                                BasedOnNumberOfPaymentsPaymentGenerator $basedOnNumberOfPaymentsPaymentGenerator,
                                                RelativeDaysPaymentGenerator $relativeDaysPaymentGenerator
                                            ) {
                                                /** @var Loan $loan */
                                                $loan = $this->getOwnerRecord();

                                                $type = $get('type');
                                                $startDate = $get('first_payment_date');

                                                if ($type === 'custom') {
                                                    return;
                                                }

                                                if ($type === 'payments') {
                                                    $numberOfPayments = $get('number_of_payments');
                                                    $frequencyUnit = $get('frequency_unit');
                                                    $frequencyType = $get('frequency_type');

                                                    $set(
                                                        'payments',
                                                        $basedOnNumberOfPaymentsPaymentGenerator->handle(
                                                            $loan->amount,
                                                            $numberOfPayments,
                                                            $frequencyUnit,
                                                            $frequencyType,
                                                            $startDate
                                                        )
                                                    );

                                                    return;
                                                }

//                                                logger()->debug('everyDay', [
//                                                    'every_day_of_month' => ,
//                                                ]);
                                                $set(
                                                    'payments',
                                                    $relativeDaysPaymentGenerator->handle(
                                                        $loan->amount,
                                                        $startDate,
                                                        collect($get('every_of_month'))->values()->toArray()
                                                    )
                                                );
                                            }
                                        ),
                                    Forms\Components\Wizard\Step::make('Esquema')
                                        ->schema([
                                            Forms\Components\Repeater::make('payments')
                                                ->columns()
                                                ->addable(function (Forms\Get $get) {
                                                    $total = $this->getOwnerRecord()->amount;
                                                    $payments = collect($get('payments'));

                                                    $paymentsSum = $payments->reduce(fn ($accum, $payment) => $accum += $payment['amount'], 0.0);

                                                    return $total !== $paymentsSum;
                                                })
                                                ->schema([
                                                    Forms\Components\TextInput::make('amount')
                                                        ->label('Cantidad')
                                                        ->prefix('$')
                                                        ->numeric()
                                                        ->required(),
                                                    Forms\Components\DatePicker::make('scheduled_at')
                                                        ->label('Fecha de pago')
                                                        ->default(Carbon::now())
                                                        ->prefixIcon('heroicon-o-calendar')
                                                        ->native(false)
                                                        ->closeOnDateSelection()
                                                        ->required(),
                                                ])
                                                ->live()
                                                ->rules([
                                                    fn (Forms\Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                                                        $total = $this->getOwnerRecord()->amount;
                                                        $payments = collect($get('payments'));

                                                        $paymentsSum = $payments->reduce(fn ($accum, $payment) => $accum += $payment['amount'], 0.0);

                                                        if ($paymentsSum !== $total) {
                                                            $fail(
                                                                sprintf(
                                                                    'Los totales no coinciden, se espera un total de %s, pero la suma de los registros es de %s con una diferencia de %s.',
                                                                    $total,
                                                                    $paymentsSum,
                                                                    round($total - $paymentsSum, 2)
                                                                )
                                                            );
                                                        }
                                                    }
                                                ])
                                        ]),
                                ])
                            ]),
                    ])
                    ->action(function (array $data) {
                        /** @var Loan $loan */
                        $loan = $this->getOwnerRecord();
                        $oldPayments = $loan->payments()->get();
                        $transactionIds = $oldPayments->pluck('transaction_id')->filter()->values();
                        DB::table(app(Transaction::class)->getTable())->whereIn('id', $transactionIds)->delete();
                        $loan->payments()->delete();

                        $payments = collect($data['payments']);

                        $userId = auth()->id();
                        $loanId = $loan->id;
                        $createdAt = Carbon::now()->format('Y-m-d H:i:s');
                        DB::table(app(LoanPayment::class)->getTable())
                            ->insert(
                                $payments->map(fn (array $payment) => [
                                    'amount' => $payment['amount'],
                                    'scheduled_at' => $payment['scheduled_at'],
                                    'paid_at' => null,
                                    'user_id' => $userId,
                                    'loan_id' => $loanId,
                                    'transaction_id' => null,
                                    'created_at' => $createdAt,
                                ])->toArray()
                            );

                        Notification::make('created')
                            ->success()
                            ->title('Plan de pagos creado.')
                            ->send();
                    })
            ])
            ->actions([
                Tables\Actions\Action::make('pay_action')
                    ->label('TO DO pay action')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }
}
