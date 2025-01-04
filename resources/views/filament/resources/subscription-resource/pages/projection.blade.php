<x-filament-panels::page>
    <div class="py-10">
        <div class="mt-8 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead>
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-3">Subscripción</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Cantidad original</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Frecuencia</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Cantidad {{ $pageTitle }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($subscriptions as $subscription)
                            <tr>
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-3">{{ $subscription['name'] }}</td>
                                <td class="whitespace-nowrap px-3 py-4 pr-3 text-sm font-medium text-gray-900 text-right">{{ as_money($subscription['amount']) }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $subscription['frequency'] }}</td>
                                <td class="whitespace-nowrap px-3 py-4 pr-3 text-sm font-medium text-gray-900 text-right">{{ as_money($subscription['projectionAmount']) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-3"></td>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900"></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900">
                                Total {{ $pageTitle }}:
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 pr-3 text-sm font-medium text-gray-900 text-right">{{ as_money($total) }}</td>
                        </tr>
                        <!-- More people... -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
