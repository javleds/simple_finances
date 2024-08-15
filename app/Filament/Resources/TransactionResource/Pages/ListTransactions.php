<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Actions\CreateTransferAction;
use App\Filament\Resources\TransactionResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateTransferAction::make(),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            Tab::make('Hasta hoy')->modifyQueryUsing(fn (Builder $query) => $query->beforeOrEqualsTo(Carbon::now())),
            Tab::make('Todas'),
        ];
    }
}
