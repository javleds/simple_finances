<x-filament-panels::page>
    <ul role="list" class="divide-y divide-white/5">
        @foreach($subscriptions as $i => $subscription)
            <x-projection-item
                :name="$subscription['name']"
                :amount="$subscription['amount']"
                :frequency="$subscription['frequency']"
                :projectionAmount="$subscription['projectionAmount']" />
        @endforeach

        <x-projection-item
            name="Total"
            amount=""
            frequency=""
            projectionAmount="{{ $total }}"
            color="indigo"
            hide-indicator
        />
    </ul>
</x-filament-panels::page>
