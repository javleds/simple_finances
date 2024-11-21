<?php

namespace App\Filament\Resources\AccountResource\RelationManagers;

use App\Filament\Tables\Columns\ProgressColumn;
use App\Models\FinancialGoal;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FinancialGoalsRelationManager extends RelationManager
{
    protected static string $relationship = 'financialGoals';
    protected static ?string $title = 'Metas financieras';
    protected static ?string $modelLabel = 'Meta financiera';
    protected static ?string $pluralModelLabel = 'Metas financieras';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre de la meta')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->label('Cantidad objetivo')
                    ->numeric()
                    ->required()
                    ->prefix('$')
                    ->minValue(0.0),
                Forms\Components\DatePicker::make('must_completed_at')
                    ->label('Fecha límite')
                    ->placeholder('Dejar en blanco si no hay fecha límite')
                    ->prefixIcon('heroicon-o-calendar')
                    ->native(false)
                    ->closeOnDateSelection(),
                Forms\Components\Select::make('user_id')
                    ->options(fn () => $this->getOwnerRecord()->users()->get()->pluck('name', 'id'))
                    ->default(fn () => auth()->id())
                    ->label('Usuario')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Objetivo')
                    ->formatStateUsing(fn ($state) => as_money($state)),
                ProgressColumn::make('progress')
                    ->label('Progreso'),
                Tables\Columns\TextColumn::make('must_completed_at')
                    ->label('Fecha límite')
                    ->date()
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
