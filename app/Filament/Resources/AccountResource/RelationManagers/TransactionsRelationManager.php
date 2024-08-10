<?php

namespace App\Filament\Resources\AccountResource\RelationManagers;

use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\Carbon;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Livewire\Component;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transacciones';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function getTabs(): array
    {
        /** @var Account $account */
        $account = $this->getOwnerRecord();

        if (!$account->isCreditCard()) {
            return [];
        }

        return [
            Tab::make('En peridoo')->modifyQueryUsing(fn (Builder $query) => $query->beforeOf($this->getOwnerRecord()->next_cutoff_date)),
            Tab::make('Todas'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('concept')
                    ->label('Concepto')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->label('Cantidad')
                    ->prefix('$')
                    ->required()
                    ->numeric(),
                Forms\Components\ToggleButtons::make('type')
                    ->label('Tipo')
                    ->inline()
                    ->grouped()
                    ->options(TransactionType::class)
                    ->default(TransactionType::Outcome)
                    ->required(),
                Forms\Components\DatePicker::make('scheduled_at')
                    ->label('Fecha')
                    ->prefixIcon('heroicon-o-calendar')
                    ->default(Carbon::now())
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('scheduled_at', 'desc')->orderBy('created_at', 'desc'))
            ->recordTitleAttribute('concept')
            ->columns([
                Tables\Columns\TextColumn::make('concept')
                    ->label('Concepto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Cantidad')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => sprintf('$ %s', number_format($state, 2)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Fecha de compra')
                    ->dateTime('F d, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(TransactionType::class)
                    ->multiple()
                    ->searchable(),
                Tables\Filters\Filter::make('scheduled_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde')
                            ->native(false)
                            ->closeOnDateSelection(),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta')
                            ->native(false)
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('scheduled_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('scheduled_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Desde ' . Carbon::parse($data['from'])->toFormattedDateString())
                                ->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Hasta ' . Carbon::parse($data['until'])->toFormattedDateString())
                                ->removeField('until');
                        }

                        return $indicators;
                    })
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_differed_transaction')
                    ->hidden(fn () => !$this->getOwnerRecord()->isCreditCard())
                    ->label('Crear Egreso Diferido')
                    ->color(Color::Amber)
                    ->form([
                        Forms\Components\Group::make()
                            ->columns()
                            ->schema([
                                Forms\Components\TextInput::make('concept')
                                    ->label('Concepto')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Cantidad')
                                    ->prefix('$')
                                    ->required()
                                    ->numeric(),
                                Forms\Components\DatePicker::make('scheduled_at')
                                    ->label('Fecha')
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->default(Carbon::now())
                                    ->native(false)
                                    ->closeOnDateSelection()
                                    ->required(),
                                Forms\Components\TextInput::make('payments')
                                    ->label('No. pagos')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                        /** @var Account $account */
                                        $account = $this->getOwnerRecord();

                                        $amount = floatval($get('amount'));
                                        $payments = intval($get('payments'));
                                        $date = Carbon::make($get('scheduled_at'));

                                        if ($payments <= 0) {
                                            return;
                                        }

                                        $paymentSchema = [];
                                        $paymentAmount = round($amount / $payments, 2);

                                        $cutoffDay = $account->cutoff_day;

                                        $date = $date->day < $cutoffDay
                                            ? $date->setDay($cutoffDay)->clone()
                                            : $date->setDay($cutoffDay)->addMonth()->clone();

                                        $paymentSchemaTotal = 0.0;
                                        foreach (range(1, $payments) as $payment) {
                                            $paymentSchema[] = [
                                                'amount' => $paymentAmount,
                                                'scheduled_at' => $date->clone(),
                                            ];

                                            $paymentSchemaTotal += $paymentAmount;

                                            $date->addMonth();
                                        }

                                        $difference = abs($amount - $paymentSchemaTotal);

                                        if ($paymentSchemaTotal > $amount) {
                                            $value = $paymentSchema[count($paymentSchema) - 1]['amount'];
                                            $paymentSchema[count($paymentSchema) - 1]['amount'] = round($value - $difference, 2);
                                        }

                                        if ($paymentSchemaTotal < $amount) {
                                            $value = $paymentSchema[count($paymentSchema) - 1]['amount'];
                                            $paymentSchema[count($paymentSchema) - 1]['amount'] = round($value + $difference, 2);
                                        }

                                        $set('transactions', $paymentSchema);
                                    })
                                    ->minValue(1)
                                    ->required(),
                                Forms\Components\Repeater::make('transactions')
                                    ->columnSpanFull()
                                    ->label('Esquema de pagos')
                                    ->minItems(fn (Forms\Get $get) => $get('payments'))
                                    ->maxItems(fn (Forms\Get $get) => $get('payments'))
                                    ->columns()
                                    ->schema([
                                        Forms\Components\TextInput::make('amount')
                                            ->label('Cantidad')
                                            ->prefix('$')
                                            ->required()
                                            ->numeric(),
                                        Forms\Components\DatePicker::make('scheduled_at')
                                            ->label('Fecha')
                                            ->prefixIcon('heroicon-o-calendar')
                                            ->default(Carbon::now())
                                            ->native(false)
                                            ->closeOnDateSelection()
                                            ->required(),
                                    ])
                                    ->rules([
                                        fn (Forms\Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                            $amount = floatval($get('amount'));
                                            $transactionsTotal = collect($get('transactions'))
                                                ->reduce(fn (float $total, array $row) => $total += floatval($row['amount']), 0.0);

                                            if ($amount !== $transactionsTotal) {
                                                $fail(
                                                    sprintf(
                                                        'La suma del esquema de pagos ($ %s) debe ser igual a la cantidad del egreso ($ %s)',
                                                        number_format($transactionsTotal, 2),
                                                        number_format($amount, 2),
                                                    )
                                                );
                                            }
                                        }
                                    ])
                            ])
                    ])
                    ->action(function (array $data, Component $livewire) {
                        /** @var Account $account */
                        $account = $this->getOwnerRecord();

                        $concept = $data['concept'];
                        $amount = floatval($data['amount']);
                        $date = $data['scheduled_at'];
                        $payments = intval($data['payments']);
                        $transactionsToCreate = $data['transactions'];

                        $transactions = collect();
                        foreach ($transactionsToCreate as $i => $transaction) {
                            $transactions->add(
                                $account->transactions()->create([
                                    'concept' => sprintf('%s - %s de %s (Total $ %s)', $concept, $i + 1, $payments, number_format($amount, 2)),
                                    'amount' => $transaction['amount'],
                                    'scheduled_at' => $transaction['scheduled_at'],
                                    'type' => TransactionType::Outcome,
                                ])
                            );
                        }

                        event(new BulkTransactionSaved($transactions));

                        Notification::make('defer_outcome_done')
                            ->success()
                            ->title('Transaccion creada')
                            ->send();

                        $livewire->dispatch('refreshAccount');
                    }),
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Crear Transacción')
                    ->createAnother(false)
                    ->after(function (Transaction $record, Component $livewire) {
                        event(new TransactionSaved($record));

                        $livewire->dispatch('refreshAccount');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->after(function (Transaction $record, Component $livewire) {
                        event(new TransactionSaved($record));

                        $livewire->dispatch('refreshAccount');
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->after(function (Transaction $record, Component $livewire) {
                        event(new TransactionSaved($record));

                        $livewire->dispatch('refreshAccount');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function (Collection $records, Component $livewire) {
                            event(new BulkTransactionSaved($records));

                            $livewire->dispatch('refreshAccount');
                        }),
                ]),
            ]);
    }
}
