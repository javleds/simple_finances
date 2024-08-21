<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    #[On('refreshLoan')]
    public function refresh(): void
    {
    }
}
