<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Events\AccountSaved;
use App\Filament\Resources\AccountResource;
use App\Models\Account;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function getRedirectUrl(): string
    {
        return AccountResource::getUrl();
    }

    public function mutateFormDataBeforeCreate(array $data): array
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

    public function afterCreate(): void
    {
        event(new AccountSaved(Account::find($this->getRecord()->id)));
    }
}
