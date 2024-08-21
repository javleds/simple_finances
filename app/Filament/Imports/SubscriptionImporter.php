<?php

namespace App\Filament\Imports;

use App\Enums\Frequency;
use App\Models\Subscription;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class SubscriptionImporter extends Importer
{
    protected static ?string $model = Subscription::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Nombre')
                ->exampleHeader('Nombre')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('amount')
                ->label('Cantidad')
                ->exampleHeader('Cantidad')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric']),
            ImportColumn::make('frequency_unit')
                ->label('Frecuencia Cada')
                ->exampleHeader('Frecuencia Cada')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('frequency_type')
                ->label('Frecuencia Unidad')
                ->exampleHeader('Frecuencia Unidad')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('started_at')
                ->label('Fecha de contrato')
                ->exampleHeader('Fecha de contract')
                ->requiredMapping()
                ->rules(['required', 'date']),
        ];
    }

    public function resolveRecord(): ?Subscription
    {
        return new Subscription();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Importación finalizada con ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' importados.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' registros fallaron.';
        }

        return $body;
    }

    public function beforeValidate(): void
    {
        if (str_contains(mb_strtolower($this->data['frequency_type']), 'mes')) {
            $this->data['frequency_type'] = Frequency::Month;
            return;
        }

        if (str_contains(mb_strtolower($this->data['frequency_type']), 'año')) {
            $this->data['frequency_type'] = Frequency::Year;
            return;
        }

        if (str_contains(mb_strtolower($this->data['frequency_type']), 'dia')) {
            $this->data['frequency_type'] = Frequency::Day;
            return;
        }

        if (str_contains(mb_strtolower($this->data['frequency_type']), 'día')) {
            $this->data['frequency_type'] = Frequency::Day;
            return;
        }

        $this->data['frequency_type'] = Frequency::Month;
    }
}
