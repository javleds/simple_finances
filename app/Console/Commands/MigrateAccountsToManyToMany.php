<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

class MigrateAccountsToManyToMany extends Command
{
    protected $signature = 'app:migrate-accounts-to-many-to-many';

    protected $description = 'Command description';

    public function handle(): int
    {
        $accounts = Account::withoutGlobalScopes()->get();

        progress('Migrating accounts', $accounts, function (Account $account) {
            $account->users()->attach($account->user_id);
        });

        return self::SUCCESS;
    }
}
