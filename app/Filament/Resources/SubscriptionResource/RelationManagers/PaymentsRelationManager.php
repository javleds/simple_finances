<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use App\Enums\TransactionStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\GenerateSubscriptionPaymentSchema;
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
use Illuminate\Support\Carbon;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'Esquema de pagos';
    protected static ?string $modelLabel = 'Pago';

    public function getTabs(): array
    {
        return [
            Tab::make('Pendiente')->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::Pending)),
            Tab::make('Pagados')->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::Paid)),
            Tab::make('Todos'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('scheduled_at')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('scheduled_at')
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Fecha de pago')
                    ->date(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Cantidad')
                    ->formatStateUsing(fn ($state) => as_money($state)),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
//                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('make_payments')
                    ->label('Generar pagos')
                    ->form([
                        Forms\Components\Group::make([
                            Forms\Components\DatePicker::make('from')
                                ->label('Desde')
                                ->required()
                                ->default(function () {
                                    $now = Carbon::now();
                                    $startDate = $this->getOwnerRecord()->started_at;

                                    if ($this->getOwnerRecord()->payments()->count() === 0) {
                                        return $startDate;
                                    }

                                    return $now;
                                })
                                ->native(false)
                                ->suffixIcon('heroicon-o-calendar')
                                ->closeOnDateSelection(),
                            Forms\Components\DatePicker::make('to')
                                ->label('Hasta')
                                ->required()
                                ->default(fn () => Carbon::now()->endOfYear())
                                ->native(false)
                                ->closeOnDateSelection()
                                ->suffixIcon('heroicon-o-calendar')
                                ->minDate(fn () => $this->getOwnerRecord()->started_at),
                        ])->columns()
                    ])
                ->action(function(GenerateSubscriptionPaymentSchema $paymentSchema, array $data) {
                    $paymentSchema->handle(
                        $this->getOwnerRecord(),
                        Carbon::make($data['from']),
                        Carbon::make($data['to'])
                    );

                    /** @var Subscription $subscription */
                    $subscription = $this->getOwnerRecord();
                    $subscription->next_payment_date = $subscription->payments()->where('status', PaymentStatus::Pending)->first()?->scheduled_at;
                    $subscription->save();

                    Notification::make('payments_created')
                        ->success()
                        ->title('Esquema de pagos generado.')
                        ->send();
                })
            ])
            ->actions([
                Tables\Actions\Action::make('pay')
                    ->hidden(fn (SubscriptionPayment $record) => $record->isPaid())
                    ->label('Pagar')
                    ->color(Color::Teal)
                    ->form([
                        Forms\Components\Select::make('account_id')
                            ->options(fn () => Account::all()->pluck('name', 'id'))
                            ->default(fn () => $this->getOwnerRecord()->feed_account_id)
                    ])
                    ->action(function (array $data, SubscriptionPayment $record) {
                        $record->status = PaymentStatus::Paid;
                        $record->save();

                        /** @var Subscription $subscription */
                        $subscription = $this->getOwnerRecord();
                        $subscription->next_payment_date = $subscription->payments()->where('status', PaymentStatus::Pending)->first()?->scheduled_at;
                        $subscription->previous_payment_date = $subscription->payments()->where('status', PaymentStatus::Paid)->first()?->scheduled_at;
                        $subscription->save();

                        if ($data['account_id'] === null || $data['account_id'] === '') {
                            Notification::make('payment_created')
                                ->success()
                                ->title('Estatus actualizado.')
                                ->send();

                            return;
                        }

                        $account = Account::find($data['account_id']);

                        if (!$account) {
                            return;
                        }

                        $account->transactions()->create([
                            'concept' => sprintf(
                                'Pago de subscripción "%s" - %s',
                                $this->getOwnerRecord()->name,
                                as_money($record->amount)
                            ),
                            'amount' => $record->amount,
                            'scheduled_at' => Carbon::now(),
                            'type' => TransactionType::Outcome,
                            'status' => TransactionStatus::Completed,
                        ]);

                        Notification::make('payment_created')
                            ->success()
                            ->title('Estatus actualizado y registrado en transacciones.')
                            ->send();
                    }),
                Tables\Actions\Action::make('restore')
                    ->requiresConfirmation()
                    ->hidden(fn (SubscriptionPayment $record) => $record->isPending())
                    ->label('Restablecer')
                    ->color(Color::Amber)
                    ->action(function (SubscriptionPayment $record) {
                        $record->status = PaymentStatus::Pending;
                        $record->save();

                        /** @var Subscription $subscription */
                        $subscription = $this->getOwnerRecord();
                        $subscription->next_payment_date = $subscription->payments()->where('status', PaymentStatus::Pending)->first()?->scheduled_at;
                        $subscription->previous_payment_date = $subscription->payments()->where('status', PaymentStatus::Paid)->first()?->scheduled_at;
                        $subscription->save();

                        Notification::make('payment_created')
                            ->success()
                            ->title('Estatus actualizado.')
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        /** @var Subscription $subscription */
                        $subscription = $this->getOwnerRecord();
                        $subscription->next_payment_date = $subscription->payments()->where('status', PaymentStatus::Pending)->first()?->scheduled_at;
                        $subscription->previous_payment_date = $subscription->payments()->where('status', PaymentStatus::Paid)->first()?->scheduled_at;
                        $subscription->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
