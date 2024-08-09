<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Events\AccountSaved;
use App\Filament\Resources\AccountResource;
use App\Models\Account;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return AccountResource::getUrl();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['credit_card']) {
            $today = CarbonImmutable::now();
            $cutoffDay = intval($data['cutoff_day']);

            $data['next_cutoff_date'] = $today->day < $cutoffDay
                ? $today->setDay($cutoffDay)->addMonth()->endOfDay()
                : $today->setDay($cutoffDay)->endOfDay();
        }

        return $data;
    }

    public function afterSave(): void
    {
        event(new AccountSaved(Account::find($this->getRecord()->id)));
    }
}
