<?php

namespace App\Filament\Resources\FixedIncomeResource\Pages;

use App\Filament\Resources\FixedIncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFixedIncomes extends ListRecords
{
    protected static string $resource = FixedIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
