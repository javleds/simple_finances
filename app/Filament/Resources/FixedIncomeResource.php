<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FixedIncomeResource\Pages;
use App\Filament\Resources\FixedIncomeResource\RelationManagers;
use App\Models\FixedIncome;
use App\Enums\Frequency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;

class FixedIncomeResource extends Resource
{
    protected static ?string $model = FixedIncome::class;

    protected static ?int $navigationSort = 70;

    protected static ?string $modelLabel = 'Ingreso fijo';
    protected static ?string $pluralModelLabel = 'Ingresos fijos';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('frequency')
                    ->label('Frecuencia')
                    ->options(Frequency::class)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('frequency')
                    ->label('Frecuencia')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
                        TextEntry::make('frequency')
                             ->label('Frecuencia'),
                        TextEntry::make('balance')
                            ->label('Balance')
                            ->getStateUsing(fn (FixedIncome $record): string => as_money(
                                $record->partials()->sum('amount') - $record->outcomes()->sum('amount')
                            )),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OutcomesRelationManager::class,
            RelationManagers\PartialsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFixedIncomes::route('/'),
            'create' => Pages\CreateFixedIncome::route('/create'),
            'edit' => Pages\EditFixedIncome::route('/{record}/edit'),
            'view' => Pages\ViewFixedIncome::route('/{record}'),
        ];
    }
}
