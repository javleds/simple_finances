<?php

namespace Database\Seeders;

use App\Models\NotificationType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationTypeSeederFirstRelease extends Seeder
{
    public const INVITATION_NOTIFICATION = 'Invitación a cuentas compartidas';
    public const MOVEMENTS_NOTIFICATION = 'Movimientos en cuentas compartidas';

    public const DEFAULT_NOTIFICATIONS = [
        self::INVITATION_NOTIFICATION,
        self::MOVEMENTS_NOTIFICATION,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        NotificationType::all()->each(fn ($notificationType) => $notificationType->delete());

        $notifications = [
            [
                'name' => self::INVITATION_NOTIFICATION,
                'description' => 'Recibe notificaciones cuando algún usuario te invite a una cuenta compartida.',
            ],
            [
                'name' => self::MOVEMENTS_NOTIFICATION,
                'description' => 'Recibe notificaciones cuando un usuario haga transacciones en la cuenta.',
            ],
            [
                'name' => 'Resumen semanal de cuentas compartidas',
                'description' => 'Recibe un correo semanal con los movimientos realizados en las cuentas compartidas.',
            ],
        ];

        foreach ($notifications as $notification) {
            NotificationType::create([
                'name' => $notification['name'],
                'description' => $notification['description'],
            ]);
        }
    }
}
