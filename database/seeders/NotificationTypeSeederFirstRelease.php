<?php

namespace Database\Seeders;

use App\Models\NotificationType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationTypeSeederFirstRelease extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        NotificationType::all()->each(fn ($notificationType) => $notificationType->delete());

        $notifications = [
            [
                'name' => 'Invitación a cuentas compartidas',
                'description' => 'Recibe notificaciones cuando algún usuario te invite a una cuenta compartida.',
            ],
            [
                'name' => 'Movimientos en cuentas compartidas',
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
