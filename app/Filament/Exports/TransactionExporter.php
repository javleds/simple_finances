<?php

namespace App\Filament\Exports;

use App\Models\Transaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TransactionExporter extends Exporter
{
    protected static ?string $model = Transaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('concept')
                ->label('Concepto'),
            ExportColumn::make('amount')
                ->label('Cantidad'),
            ExportColumn::make('type')
                ->label('Tipo')
                ->formatStateUsing(fn ($state) => $state->getLabel()),
            ExportColumn::make('scheduled_at')
                ->label('Fecha de pago'),
            ExportColumn::make('account.name')
                ->label('Cuenta'),
            ExportColumn::make('user.name')
                ->label('Creado por'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your transaction export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
