<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Actions\CompareAction;
use App\Filament\Actions\CreateTransferAction;
use App\Filament\Resources\AccountResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CompareAction::make(),
            CreateTransferAction::make(),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            Tab::make('Físicas')->modifyQueryUsing(fn (Builder $query) => $query->where('virtual', false)->withoutTrashed()),
            Tab::make('Virtuales')->modifyQueryUsing(fn (Builder $query) => $query->where('virtual', true)->withoutTrashed()),
            Tab::make('Archivadas')->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed()),
            Tab::make('Todas'),
        ];
    }
}
