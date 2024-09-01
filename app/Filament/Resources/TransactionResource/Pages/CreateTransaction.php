<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Enums\Action;
use App\Events\TransactionSaved;
use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getRedirectUrl(): string
    {
        return TransactionResource::getUrl();
    }

    public function afterCreate(): void
    {
        event(new TransactionSaved(Transaction::find($this->getRecord()->id), Action::Deleted));
    }
}
