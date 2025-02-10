<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Dto\AccountDto;
use App\Filament\Resources\AccountResource;
use App\Handlers\Accounts\AccountEditor;
use App\Models\Account;
use Carbon\CarbonImmutable;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $account = Account::find($record->id);

        return app(AccountEditor::class)->execute($account, AccountDto::fromFormArray($data));
    }

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
}
