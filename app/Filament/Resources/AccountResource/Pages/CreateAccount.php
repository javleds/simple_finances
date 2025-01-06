<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Dto\AccountDto;
use App\Filament\Resources\AccountResource;
use App\Handlers\Accounts\AccountCreator;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    public function __construct(
        private readonly AccountCreator $accountCreator,
    ) {}

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

    public function handleRecordCreation(array $data): Model
    {
        return $this->accountCreator->execute(AccountDto::fromFormArray($data));
    }
}
