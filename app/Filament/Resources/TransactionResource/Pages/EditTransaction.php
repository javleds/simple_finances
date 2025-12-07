<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Dto\TransactionFormDto;
use App\Filament\Resources\TransactionResource;
use App\Services\Transaction\TransactionUpdater;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data['id'] = $record->id;

        return app(TransactionUpdater::class)->execute($record, TransactionFormDto::fromFormArray($data));
    }
}
