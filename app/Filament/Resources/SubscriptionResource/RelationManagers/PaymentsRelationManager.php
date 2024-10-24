<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use App\Services\GenerateSubscriptionPaymentSchema;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
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

                    Notification::make('payments_created')
                        ->success()
                        ->title('Esquema de pagos generado.')
                        ->send();
                })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
