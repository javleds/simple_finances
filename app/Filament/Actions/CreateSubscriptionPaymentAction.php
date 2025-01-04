<?php

namespace App\Filament\Actions;

use App\Enums\Action as UserAction;
use App\Enums\PaymentStatus;
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
            ->hidden(fn (Subscription $record) => $record->payments()->where('status', PaymentStatus::Pending)->count() === 0)
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
                            ->columnSpanFull(),
                        TextInput::make('amount')
                            ->label('Cantidad')
                            ->prefix('$')
                            ->default(fn (Subscription $record) => $record->amount)
                            ->numeric(),
                        DatePicker::make('scheduled_at')
                            ->label('Fecha')
                            ->prefixIcon('heroicon-o-calendar')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->default(Carbon::now())
                            ->maxDate(Carbon::now()),
                    ])
            ])
            ->action(function (array $data, Subscription $record) {
                $subscription = Subscription::find($record->id);
                $payment = $subscription->payments()->where('status', PaymentStatus::Pending)->first();
                $payment->update(['status' => PaymentStatus::Paid]);

                $subscription->next_payment_date = $subscription->payments()->where('status', PaymentStatus::Pending)->first()?->scheduled_at;
                $subscription->previous_payment_date = $subscription->payments()->where('status', PaymentStatus::Paid)->first()?->scheduled_at;
                $subscription->save();

                if ($data['feed_account_id'] !== null && $data['feed_account_id'] !== '') {
                    $transaction = Transaction::create([
                        'amount' => $data['amount'],
                        'type' => TransactionType::Outcome,
                        'scheduled_at' => $data['scheduled_at'],
                        'concept' => sprintf('Pago de subscripción %s.', $record->name),
                        'account_id' => $data['feed_account_id'],
                    ]);

                    event(new TransactionSaved($transaction, UserAction::Created));
                }

                Notification::make('saved')
                    ->success()
                    ->title('Pago creado.')
                    ->send();
            });
    }
}
