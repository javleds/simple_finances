<?php

namespace App\Filament\Resources\AccountResource\RelationManagers;

use App\Enums\Action;
use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Events\TransactionSaved;
use App\Filament\Actions\DeferredTransactionAction;
use App\Filament\Exports\TransactionExporter;
use App\Filament\Filters\DateRangeFilter;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Components\Tab;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transacciones';
    protected static ?string $label = 'Transacción';

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
            Tab::make('Hasta hoy')->modifyQueryUsing(fn (Builder $query) => $query->beforeOrEqualsTo($this->getOwnerRecord()->next_cutoff_date)),
            Tab::make('Todas'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\ToggleButtons::make('type')
                    ->label('Tipo')
                    ->inline()
                    ->grouped()
                    ->options(TransactionType::class)
                    ->default(TransactionType::Outcome)
                    ->required()
                    ->columnSpanFull()
                    ->live(),
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
                Forms\Components\Select::make('financial_goal_id')
                    ->relationship('financialGoal', 'name')
                    ->label('Meta financiera')
                    ->disabled(fn (Forms\Get $get) => $get('type') === TransactionType::Outcome),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderBy('scheduled_at', 'desc')->orderBy('created_at', 'desc'))
            ->recordTitleAttribute('concept')
            ->columns([
                Tables\Columns\TextColumn::make('concept')
                    ->label('Concepto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Cantidad')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => as_money($state))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Fecha')
                    ->dateTime('F d, Y')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creado por')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(TransactionType::class)
                    ->multiple()
                    ->searchable(),
                DateRangeFilter::make('scheduled_at', 'Fecha de pago'),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Creado por')
                    ->options(fn () => $this->getOwnerRecord()->users()->get()->pluck('name', 'id'))
                    ->preload()
                    ->searchable()
                    ->multiple(),
            ])
            ->headerActions([
                DeferredTransactionAction::makeWithOwnerRecord($this->getOwnerRecord()),
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Crear Transacción')
                    ->after(function (Transaction $record, Component $livewire) {
                        event(new TransactionSaved($record, Action::Created));

                        $livewire->dispatch('refreshAccount');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->after(function (Transaction $record, Component $livewire) {
                        event(new TransactionSaved($record, Action::Updated));

                        $livewire->dispatch('refreshAccount');
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->after(function (Transaction $record, Component $livewire) {
                        event(new TransactionSaved($record, Action::Deleted));

                        $livewire->dispatch('refreshAccount');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Exportar seleccionados')
                        ->exporter(TransactionExporter::class)
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function (Collection $records, Component $livewire) {
                            event(new BulkTransactionSaved($records));

                            $livewire->dispatch('refreshAccount');
                        }),
                ]),
            ]);
    }
}
