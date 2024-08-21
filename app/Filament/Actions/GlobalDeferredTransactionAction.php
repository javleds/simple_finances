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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class GlobalDeferredTransactionAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('add_global_differed_transaction')
            ->label('MSI')
            ->color(Color::Amber)
            ->form([
                Group::make()
                    ->columns()
                    ->schema([
                        Select::make('account_id')
                            ->label('Cuenta')
                            ->options(fn () => Account::where('credit_card', true)->get()->pluck('transfer_balance_label', 'id'))
                            ->required(),
                        TextInput::make('concept')
                            ->label('Concepto')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('amount')
                            ->label('Cantidad')
                            ->prefix('$')
                            ->required()
                            ->numeric(),
                        DatePicker::make('scheduled_at')
                            ->label('Fecha')
                            ->prefixIcon('heroicon-o-calendar')
                            ->default(Carbon::now())
                            ->native(false)
                            ->closeOnDateSelection()
                            ->required(),
                        TextInput::make('payments')
                            ->label('No. pagos')
                            ->numeric()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                /** @var Account $account */
                                $account = Account::find($get('account_id'));

                                if (!$account) {
                                    return;
                                }

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
                        Repeater::make('transactions')
                            ->columnSpanFull()
                            ->label('Esquema de pagos')
                            ->minItems(fn (Get $get) => $get('payments'))
                            ->maxItems(fn (Get $get) => $get('payments'))
                            ->columns()
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Cantidad')
                                    ->prefix('$')
                                    ->required()
                                    ->numeric(),
                                DatePicker::make('scheduled_at')
                                    ->label('Fecha')
                                    ->prefixIcon('heroicon-o-calendar')
                                    ->default(Carbon::now())
                                    ->native(false)
                                    ->closeOnDateSelection()
                                    ->required(),
                            ])
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
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
                $account = Account::find($data['account_id']);

                $concept = $data['concept'];
                $amount = floatval($data['amount']);
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
            });
    }
}
