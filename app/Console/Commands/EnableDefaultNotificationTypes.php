<?php

namespace App\Console\Commands;

use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Console\Command;
use function Laravel\Prompts\progress;

class EnableDefaultNotificationTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:enable-default-notification-types';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'After the release, this command will iterate over all the users to enable the default notifications.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::withoutGlobalScopes()->get();
        $notificationTypes = NotificationType::whereIn(
            'name',
            ['Invitación a cuentas compartidas', 'Movimientos en cuentas compartidas']
        )->get()->pluck('id')->toArray();

        progress('Enabling notifications', $users, function (User $user) use ($notificationTypes) {
            $user->notificationTypes()->sync($notificationTypes);
        });

        $this->output->writeln('');

        return self::SUCCESS;
    }
}
