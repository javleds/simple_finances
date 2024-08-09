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
                    ->searchable(),
                Tables\Columns\TextColumn::make('balance')
                    ->alignRight()
                    ->money(locale: 'mx')
                    ->sortable(),
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('add_transaction')
                    ->label('Transacción')
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
                        ->money(locale: 'mx'),
                    TextEntry::make('description')
                        ->label('Descripción')
                        ->columnSpanFull(),
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
