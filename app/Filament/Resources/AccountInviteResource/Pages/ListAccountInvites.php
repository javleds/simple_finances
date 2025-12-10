<?php

namespace App\Filament\Resources\AccountInviteResource\Pages;

use App\Filament\Resources\AccountInviteResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountInvites extends ListRecords
{
    protected static string $resource = AccountInviteResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
