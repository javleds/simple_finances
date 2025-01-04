@props(['name', 'amount', 'frequency', 'projectionAmount', 'color' => 'gray', 'hideIndicator' => false])

@php($colorClass = $color === 'gray' ? 'bg-gray-400/10 ring-gray-400/20 text-gray-400' : 'bg-indigo-400/10 ring-indigo-400/20 text-indigo-400')

<li class="relative flex items-center space-x-4 py-4">
    <div class="min-w-0 flex-auto">
        <div class="flex items-center gap-x-3">
            @if(!$hideIndicator)
                <div class="flex-none rounded-full bg-green-600/10 p-1 text-green-500">
                    <div class="size-2 rounded-full bg-current"></div>
                </div>
            @endif
            <h2 class="min-w-0 text-sm/6 font-semibold text-white">
                <a href="#" class="flex gap-x-2">
                    <span class="truncate">{{ $name }}</span>
                    <span class="absolute inset-0"></span>
                </a>
            </h2>
        </div>
        @if($amount)
            <div class="mt-3 flex items-center gap-x-2.5 text-xs/5 text-gray-400">
                <p class="truncate">{{ as_money($amount) }}</p>
                <svg viewBox="0 0 2 2" class="size-0.5 flex-none fill-gray-300">
                    <circle cx="1" cy="1" r="1" />
                </svg>
                <p class="whitespace-nowrap">{{ $frequency }}</p>
            </div>
        @endif
    </div>
    <div class="flex-none rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset {{$colorClass}}">{{ as_money($projectionAmount) }}</div>
</li>
