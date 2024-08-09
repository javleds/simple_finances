<?php

namespace App\Filament\Resources\AccountResource\RelationManagers;

use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Events\TransactionSaved;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
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
                    ->money(locale: 'mx')
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
                Tables\Actions\CreateAction::make()
                    ->createAnother(false)
                    ->after(function (Transaction $record, Component $livewire) {
                        event(new TransactionSaved($record));

                        $livewire->dispatch('refreshAccount');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (Transaction $record, Component $livewire) {
                        event(new TransactionSaved($record));

                        $livewire->dispatch('refreshAccount');
                    }),
                Tables\Actions\DeleteAction::make()
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