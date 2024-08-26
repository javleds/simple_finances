<?php

namespace App\Filament\Resources\AccountResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvitesRelationManager extends RelationManager
{
    protected static string $relationship = 'invites';
    protected static ?string $title = 'Invitaciones';
    protected static ?string $label = 'Invitación';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->label('Correo elctrónico')
                    ->required()
                    ->maxLength(255)
                    ->email(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo electrónico'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('re_send_invite')
                    ->label('Reenviar invitación'),
                Tables\Actions\DeleteAction::make()
                    ->label('Cancelar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
