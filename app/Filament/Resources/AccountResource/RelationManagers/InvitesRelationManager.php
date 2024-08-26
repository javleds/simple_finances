<?php

namespace App\Filament\Resources\AccountResource\RelationManagers;

use App\Enums\InviteStatus;
use App\Events\AccountInviteCreated;
use App\Models\AccountInvite;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->user_id === auth()->id();
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
                    ->label('Reenviar invitación')
                    ->hidden(fn (AccountInvite $record) => $record->isAccepted())
                    ->action(function (AccountInvite $record) {
                        $record->status = InviteStatus::Pending;
                        $record->save();

                        event(new AccountInviteCreated($record));
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Cancelar')
                    ->hidden(fn (AccountInvite $record) => $record->isAccepted()),
                Tables\Actions\Action::make('revoke_user')
                    ->requiresConfirmation()
                    ->color(Color::Red)
                    ->label('Dejar de compartir')
                    ->hidden(fn (AccountInvite $record) => !$record->isAccepted())
                    ->action(function (AccountInvite $record) {
                        $user = User::withoutGlobalScopes()->where('email', $record->email)->first();
                        $record->account->users()->detach($user->id);
                        $record->delete();

                        Notification::make('user_revoked')
                            ->success()
                            ->title('Guardado.')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }
}
