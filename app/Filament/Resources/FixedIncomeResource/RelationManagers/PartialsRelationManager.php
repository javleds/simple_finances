<?php

namespace App\Filament\Resources\FixedIncomeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Component;

class PartialsRelationManager extends RelationManager
{
    protected static string $relationship = 'partials';
    protected static ?string $modelLabel = 'Ingreso parcial';
    protected static ?string $pluralModelLabel = 'Ingresos parciales';
    protected static ?string $title = 'Ingresos parciales';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->label('Monto')
                    ->numeric()
                    ->required()
                    ->prefix('$'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->formatStateUsing(fn (string $state): string => as_money($state))
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function(Component $livewire) {
                        $livewire->dispatch('refreshFixedIncome');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function(Component $livewire) {
                        $livewire->dispatch('refreshFixedIncome');
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function(Component $livewire) {
                        $livewire->dispatch('refreshFixedIncome');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
