<?php

namespace App\Filament\Resources\FixedIncomeResource\Pages;

use App\Filament\Resources\FixedIncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFixedIncome extends EditRecord
{
    protected static string $resource = FixedIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return FixedIncomeResource::getUrl();
    }

    public function getRelationManagers(): array
    {
        return [];
    }
}
