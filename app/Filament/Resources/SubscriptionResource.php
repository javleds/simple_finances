<?php

namespace App\Filament\Resources;

use App\Enums\Frequency;
use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Account;
use App\Models\Subscription;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;
    protected static ?string $label = 'Subscripción';
    protected static ?string $pluralLabel = 'Subscripciones';

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->label('Cantidad')
                    ->prefix('$')
                    ->required()
                    ->numeric(),
                Forms\Components\DatePicker::make('started_at')
                    ->label('Fecha de contratación')
                    ->prefixIcon('heroicon-o-calendar')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->default(Carbon::now())
                    ->required(),
                Forms\Components\Section::make('Frecuencia de pago')
                    ->columns()
                    ->schema([
                        Forms\Components\TextInput::make('frequency_unit')
                            ->label('Cada')
                            ->default(1)
                            ->required()
                            ->numeric(),
                        Forms\Components\Select::make('frequency_type')
                            ->label('Unidad')
                            ->options(Frequency::class)
                            ->default(Frequency::Month)
                            ->searchable()
                            ->required(),
                    ]),
                Forms\Components\DatePicker::make('finished_at')
                    ->label('Fecha de cancelación')
                    ->prefixIcon('heroicon-o-calendar')
                    ->native(false)
                    ->closeOnDateSelection(),
                Forms\Components\Select::make('feed_account_id')
                    ->label('Cuenta de alimentación')
                    ->options(fn () => Account::all()->map(fn ($a) => [$a->id => $a->transfer_balance_label])->flatten()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Cantidad')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('frequency_unit')
                    ->label('Cada')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('frequency_type')
                    ->label('Unidad'),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Fecha de contratación')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('finished_at')
                    ->label('Fecha de cancelación')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('feedAccount.name')
                    ->label('Cuenta de alimentación')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
