<x-mail::message>
# Hola, {{ $user->name }}.

Se registraron movimientos en la cuenta {{ $account->name }}:

<x-mail::panel>
@foreach ($items as $item)
{{ $item->modifier->name }} ha {{ $item->action->getLabel() }} un movimiento:
{{ $item->type->getLabel() }}: {{ $item->concept }} por {{ as_money($item->amount) }} el {{ $item->scheduled_at->translatedFormat('M d, Y') }}.
@if (! $loop->last)

@endif
@endforeach
</x-mail::panel>

<x-mail::panel>
Balance después de los movimientos: `{{ as_money($account->updateBalance()) }}`.
</x-mail::panel>

<x-mail::button :url="\App\Filament\Resources\AccountResource\Pages\ViewAccount::getUrl([$account->id])">
Ir a la cuenta
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
