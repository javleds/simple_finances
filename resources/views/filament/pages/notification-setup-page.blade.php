<x-filament-panels::page>
    <fieldset class="border-b border-t border-gray-200">
        <legend class="sr-only">Notificaciones</legend>
        <div class="divide-y divide-gray-200">
            @foreach($notificationTypes as $notification)
                <x-notification-option
                    wire:click="handleNotification({{ $notification['id'] }}, {{ $notification['checked'] }})"
                    :value="$notification['checked']"
                    :label="$notification['name']"
                    :description="$notification['description']" />
            @endforeach
        </div>
    </fieldset>

    <x-filament-panels::header heading="Notificación por cuentas" />
    <fieldset class="border-b border-t border-gray-200">
        <legend class="sr-only">Cuentas</legend>
        <div class="divide-y divide-gray-200">
            @foreach($notificableAccounts as $account)
                <x-notification-option
                    wire:click="handleAccountNotification({{ $account['id'] }}, {{ $account['checked'] }})"
                    :value="$account['checked']"
                    :label="$account['name']"
                    description="Habilita o deshabilita las notificaciones de esta cuenta." />
            @endforeach
        </div>
    </fieldset>
</x-filament-panels::page>
