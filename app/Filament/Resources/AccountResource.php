<?php

namespace App\Filament\Resources;

use App\Enums\TransactionType;
use App\Events\TransactionSaved;
use App\Filament\Resources\AccountResource\Pages;
use App\Filament\Resources\AccountResource\RelationManagers;
use App\Models\Account;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $label = 'Cuenta';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\ColorPicker::make('color')
                    ->label('Color'),
                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('credit_card')
                    ->label('¿Es tarjeta de crédito?')
                    ->default(false)
                    ->inline(false)
                    ->live(),
                Forms\Components\Group::make()
                    ->columnSpanFull()
                    ->hidden(fn (Forms\Get $get) => $get('credit_card') !== true)
                    ->live()
                    ->columns()
                    ->schema([
                        Forms\Components\TextInput::make('credit_line')
                            ->label('Línea de crédito')
                            ->numeric()
                            ->prefix('$')
                            ->required(fn (Forms\Get $get) => $get('credit_card') === true),
                        Forms\Components\TextInput::make('cutoff_day')
                            ->label('Día de corte')
                            ->numeric()
                            ->required(fn (Forms\Get $get) => $get('credit_card') === true)
                            ->minValue(1)
                            ->maxValue(31),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => as_money($state))
                    ->sortable(['balance'])
                    ->searchable(),
                Tables\Columns\TextColumn::make('scoped_balance')
                    ->label('En periodo')
                    ->alignRight()
                    ->formatStateUsing(
                        fn (Account $record) => !$record->isCreditCard()
                            ? '-'
                            : sprintf('$ %s', number_format($record->scoped_balance, 2))
                    )
                    ->sortable(['scoped_balance'])
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('name')
                    ->label('Nombre')
                    ->options(fn () => Account::all()->pluck('name', 'name'))
                    ->multiple()
                    ->searchable()
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(''),
                Tables\Actions\Action::make('add_transaction')
                    ->label('')
                    ->color(Color::Blue)
                    ->icon('heroicon-o-banknotes')
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
                            ]),
                    ])
                    ->action(function (array $data, Account $record) {
                        $transaction = $record->transactions()->create([
                            'concept' => $data['concept'],
                            'amount' => $data['amount'],
                            'type' => $data['type'],
                            'scheduled_at' => $data['scheduled_at'],
                        ]);

                        event(new TransactionSaved($transaction));

                        Notification::make('transaction_added')
                            ->success()
                            ->title('Transacción creada')
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->label(''),
                Tables\Actions\DeleteAction::make()
                    ->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
               Section::make('Datos generales')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')
                        ->label('Nombre'),
                    ColorEntry::make('color')
                        ->label('Color'),
                    TextEntry::make('balance')
                        ->label('Balance')
                        ->formatStateUsing(fn ($state) => as_money($state)),
                    TextEntry::make('description')
                        ->label('Descripción')
                        ->default('-')
                        ->columnSpanFull(),
                    TextEntry::make('credit_line')
                        ->label('línea de crédito')
                        ->formatStateUsing(fn ($state) => as_money($state)),
                    TextEntry::make('next_cutoff_date')
                        ->label('Fecha de corte')
                        ->dateTime('F d,Y')
                        ->numeric(),
                    TextEntry::make('scoped_balance')
                        ->label('Balance del periodo')
                        ->formatStateUsing(fn ($state) => as_money($state)),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'view' => Pages\ViewAccount::route('/{record}'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
