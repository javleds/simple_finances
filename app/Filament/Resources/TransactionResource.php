<?php

namespace App\Filament\Resources;

use App\Enums\Action;
use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Events\TransactionSaved;
use App\Filament\Exports\TransactionExporter;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static ?string $label = 'Transaccion';
    protected static ?string $pluralLabel = 'Transacciones';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
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
                Forms\Components\Select::make('account_id')
                    ->label('Cuenta')
                    ->options(fn () => Account::all()->pluck('transfer_balance_label', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderBy('scheduled_at', 'desc')->orderBy('created_at', 'desc'))
            ->groups([
                Tables\Grouping\Group::make('account.name')->label('Cuenta'),
                Tables\Grouping\Group::make('scheduled_at')->label('Fecha'),
            ])
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
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make('income')
                            ->query(fn (QueryBuilder $query) => $query->where('type', TransactionType::Income))
                            ->formatStateUsing(fn ($state) => as_money($state))
                            ->label('Ingresos'),
                        Tables\Columns\Summarizers\Sum::make('outcome')
                            ->query(fn (QueryBuilder $query) => $query->where('type', TransactionType::Outcome))
                            ->formatStateUsing(fn ($state) => as_money($state))
                            ->label('Egresos'),
                    ]),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Cuenta')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Fecha')
                    ->dateTime('F d, Y')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('account_id')
                    ->label('Cuenta')
                    ->options(fn () => Account::all()->pluck('name', 'id'))
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(TransactionType::class)
                    ->multiple()
                    ->searchable(),
                DateRangeFilter::make('scheduled_at', 'Fecha de pago'),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Creado por')
                    ->options(function () {
                        $accounts = auth()->user()->accounts()->get();
                        $userIds = DB::table('account_user')->whereIn('account_id', $accounts->pluck('id'))->get()->pluck('user_id');

                        return User::whereIn('id', $userIds)
                            ->get()
                            ->pluck('name', 'id');
                    })
                    ->preload()
                    ->searchable()
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(''),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->after(function (Transaction $record) {
                        event(new TransactionSaved($record, Action::Deleted));
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Exportar seleccionados')
                        ->exporter(TransactionExporter::class)
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function (Collection $records) {
                            event(new BulkTransactionSaved($records));
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
