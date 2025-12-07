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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $subTransactions = $this->getRecord()->subTransactions()->with('user')->get();

        if ($subTransactions->isEmpty()) {
            $data['split_between_users'] = false;
            $data['user_payments'] = [];

            return $data;
        }

        $total = $subTransactions->sum('amount');

        $data['split_between_users'] = true;
        $data['user_payments'] = $subTransactions->map(function ($subTransaction) use ($total) {
            $percentage = $total > 0 ? round(($subTransaction->amount / $total) * 100, 2) : 0.0;

            return [
                'user_id' => $subTransaction->user_id,
                'name' => $subTransaction->user?->name ?? '',
                'percentage' => $percentage,
            ];
        })->toArray();

        return $data;
    }
}
