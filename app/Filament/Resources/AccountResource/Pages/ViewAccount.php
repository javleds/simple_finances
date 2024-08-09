<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    #[On('refreshAccount')]
    public function refresh(): void
    {
    }
}
