<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Events\TransactionSaved;
use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return TransactionResource::getUrl();
    }

    public function afterSave(): void
    {
        event(new TransactionSaved(Transaction::find($this->getRecord()->id)));
    }
}
