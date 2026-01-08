<?php

namespace App\Filament\Resources\FixedIncomeResource\Pages;

use App\Filament\Resources\FixedIncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewFixedIncome extends ViewRecord
{
    protected static string $resource = FixedIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    #[On('refreshFixedIncome')]
    public function refresh(): void {}
}
