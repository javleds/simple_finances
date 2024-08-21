<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Imports\SubscriptionImporter;
use App\Filament\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ImportAction::make()
                ->label('Importar')
                ->importer(SubscriptionImporter::class),
            Actions\CreateAction::make(),
        ];
    }
}
