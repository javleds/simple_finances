<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Dto\TransactionFormDto;
use App\Filament\Resources\TransactionResource;
use App\Services\Transaction\TransactionCreator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getRedirectUrl(): string
    {
        return TransactionResource::getUrl();
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray($data));
    }
}
