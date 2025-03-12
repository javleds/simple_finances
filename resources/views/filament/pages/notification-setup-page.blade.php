<x-filament-panels::page>
    <fieldset class="border-b border-t border-gray-200">
        <legend class="sr-only">Notifications</legend>
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
</x-filament-panels::page>
