<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Imports\SubscriptionImporter;
use App\Filament\Pages\Projection;
use App\Filament\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            SubscriptionResource\Widgets\RecommendedSaving::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('monthly_projection')->label('Proyección mensual')->url(Projection::getUrl(['type' => 'monthly'])),
                Actions\Action::make('yearly_projection')->label('Proyección anual')->url(Projection::getUrl(['type' => 'yearly'])),
            ]),
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
