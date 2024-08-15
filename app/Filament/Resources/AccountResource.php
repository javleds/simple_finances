<?php

namespace App\Filament\Resources;

use App\Filament\Actions\AddTransactionShortcutAction;
use App\Filament\Actions\DirectCompareAction;
use App\Filament\Actions\DirectReceiveTransferAction;
use App\Filament\Actions\DirectSendTransferAction;
use App\Filament\Resources\AccountResource\Pages;
use App\Filament\Resources\AccountResource\RelationManagers;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $label = 'Cuenta';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 20;

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
                    ->formatStateUsing(fn (Account $account) => as_money($account->balance))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('spent')
                    ->label('Total gastado')
                    ->alignRight()
                    ->formatStateUsing(
                        fn (Account $record) => !$record->isCreditCard()
                            ? '-'
                            : as_money($record->spent)
                    )
                    ->sortable(['spent'])
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('available_credit')
                    ->label('Crédito disponible')
                    ->alignRight()
                    ->formatStateUsing(
                        fn (Account $record) => !$record->isCreditCard()
                            ? '-'
                            : as_money($record->available_credit)
                    )
                    ->sortable(['available_credit'])
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('credit_line')
                    ->label('Línea de Crédito')
                    ->alignRight()
                    ->formatStateUsing(
                        fn (Account $record) => !$record->isCreditCard()
                            ? '-'
                            : as_money($record->credit_line)
                    )
                    ->sortable(['available_credit'])
                    ->searchable()
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
                AddTransactionShortcutAction::make(),
                DirectCompareAction::make(),
                DirectReceiveTransferAction::make(),
                DirectSendTransferAction::make(),
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
                    TextEntry::make('next_cutoff_date')
                        ->label('Fecha de corte')
                        ->hidden(fn (Account $record) => !$record->isCreditCard())
                        ->dateTime('M d,Y'),
                    TextEntry::make('description')
                        ->label('Descripción')
                        ->default('-')
                        ->columnSpan(2),
                    TextEntry::make('credit_line')
                        ->label('Línea de crédito')
                        ->hidden(fn (Account $record) => !$record->isCreditCard())
                        ->formatStateUsing(fn ($state) => as_money($state)),
                    TextEntry::make('balance')
                        ->label(fn (Account $account) => sprintf('Balance %s', $account->credit_card ? $account->next_cutoff_date->translatedFormat('M d, Y') : ''))
                        ->hidden(fn (Account $record) => !$record->isCreditCard())
                        ->formatStateUsing(fn ($state) => as_money($state)),
                    TextEntry::make('spent')
                        ->label('Total gastado')
                        ->hidden(fn (Account $record) => !$record->isCreditCard())
                        ->formatStateUsing(fn ($state) => as_money($state)),
                    TextEntry::make('available_credit')
                        ->label('Crédito disponible')
                        ->hidden(fn (Account $record) => !$record->isCreditCard())
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
