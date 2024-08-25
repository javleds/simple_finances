<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanResource\Pages;
use App\Filament\Resources\LoanResource\RelationManagers\PaymentsRelationManager;
use App\Models\Account;
use App\Models\Loan;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static ?string $label = 'Préstamo';
    protected static ?int $navigationSort = 40;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

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
                Forms\Components\DatePicker::make('done_at')
                    ->label('Fecha de compra')
                    ->prefixIcon('heroicon-o-calendar')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->default(Carbon::now())
                    ->required(),
                Forms\Components\DatePicker::make('started_at')
                    ->label('Primer pago')
                    ->prefixIcon('heroicon-o-calendar')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->default(Carbon::now())
                    ->required(),
                Forms\Components\Select::make('feed_account_id')
                    ->label('Cuenta de alimentación')
                    ->options(fn () => Account::all()->pluck('transfer_balance_label', 'id'))
                    ->searchable(),
                Forms\Components\DatePicker::make('completed_at')
                    ->label('Último pago')
                    ->prefixIcon('heroicon-o-calendar')
                    ->native(false)
                    ->closeOnDateSelection(),
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
                    ->formatStateUsing(fn ($state) => as_money($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('done_at')
                    ->label('Fecha de compra')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Fecha de primer pago')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('next_payment_date')
                    ->label('Siguiente pago')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_payment_date')
                    ->label('Último pago')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('number_of_payments')
                    ->label('No. de pagos')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payments_done')
                    ->label('Pagos hechos')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid')
                    ->label('Pagado')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('to_pay')
                    ->label('Por pagar')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Fecha de término')
                    ->date()
                    ->sortable(),
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
            ->columns(3)
            ->schema([
                TextEntry::make('name')
                    ->label('Nombre'),
                TextEntry::make('amount')
                    ->label('Cantidad')
                    ->formatStateUsing(fn ($state) => as_money($state)),
                TextEntry::make('done_at')
                    ->label('Fecha de compra')
                    ->date(),
                TextEntry::make('started_at')
                    ->label('Fecha de primer pago')
                    ->date(),
                TextEntry::make('next_payment_date')
                    ->label('Siguiente pago')
                    ->date(),
                TextEntry::make('last_payment_date')
                    ->label('Último pago')
                    ->date(),
                TextEntry::make('number_of_payments')
                    ->label('No. de pagos')
                    ->numeric(),
                TextEntry::make('payments_done')
                    ->label('Pagos hechos')
                    ->numeric(),
                TextEntry::make('paid')
                    ->label('Pagado')
                    ->numeric(),
                TextEntry::make('to_pay')
                    ->label('Por pagar')
                    ->numeric(),
                TextEntry::make('completed_at')
                    ->label('Fecha de término')
                    ->date(),
                TextEntry::make('feedAccount.name')
                    ->label('Cuenta de alimentación'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'view' => Pages\ViewLoan::route('/{record}/'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
        ];
    }
}
