<?php

namespace App\Filament\Resources;

use App\Enums\InviteStatus;
use App\Filament\Resources\AccountInviteResource\Pages;
use App\Filament\Resources\AccountInviteResource\RelationManagers;
use App\Models\Account;
use App\Models\AccountInvite;
use App\Services\AccountInvites\Respond;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccountInviteResource extends Resource
{
    protected static ?string $model = AccountInvite::class;
    protected static ?string $label = 'Invitación';
    protected static ?string $pluralLabel = 'Invitaciones';

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('percentage')
                    ->label('Porcentaje asignado')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0.00)
                    ->suffix('%')
                    ->required()
                    ->hint('Dejar en 0 si no se desea asignar un porcentaje'),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\Select::make('account_id')
                    ->relationship('account', 'name')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('email', auth()->user()->email)->where('status', InviteStatus::Pending))
            ->columns([
                Tables\Columns\TextColumn::make('account_id')
                    ->label('Cuenta')
                    ->formatStateUsing(fn ($state) => Account::withoutGlobalScopes()->find($state)->name),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Invitadción de'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de invitación')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('accept_invite')
                    ->label('Aceptar')
                    ->action(function (AccountInvite $record) {
                        return app(Respond::class)->execute($record, InviteStatus::Accepted);
                    })
                    ->after(function () {
                        Notification::make()
                            ->success()
                            ->title('Guardado.')
                            ->send();
                    }),
                Tables\Actions\Action::make('decline_invite')
                    ->color(Color::Red)
                    ->label('Declinar')
                    ->action(function (AccountInvite $record) {
                        return app(Respond::class)->execute($record, InviteStatus::Declined);
                    })
                    ->after(function () {
                        Notification::make()
                            ->success()
                            ->title('Guardado.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListAccountInvites::route('/'),
        ];
    }
}
