<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Imports\SubscriptionImporter;
use App\Filament\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

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

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Activas')->query(fn (Builder $query) => $query->whereNull('finished_at')),
            'all' => Tab::make('Todas'),
            'inactive' => Tab::make('Canceladas')->query(fn (Builder $query) => $query->whereNotNull('finished_at')),
        ];
    }
}
