<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Filament\Actions\CompareAction;
use App\Filament\Actions\CreateTransferAction;
use App\Filament\Resources\AccountResource;
use App\Models\Account;
use Carbon\Carbon;
use Closure;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CompareAction::make(),
            CreateTransferAction::make(),
            Actions\CreateAction::make(),
        ];
    }
}
