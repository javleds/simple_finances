<x-mail::message>
# Hola, {{ $user->name }}.

{{ $modifier->name }} ha {{ $action->getLabel() }} un movimiento en la cuenta {{ $transaction->account->name }}:


<x-mail::panel>
{{ $transaction->type->getLabel() }}: {{ $transaction->concept }} con un monto de {{  as_money($transaction->amount) }} el {{ $transaction->scheduled_at->translatedFormat('M d, Y') }}.
</x-mail::panel>

<x-mail::panel>
Balance después del movimiento: `{{ as_money($transaction->account->updateBalance()) }}`.
</x-mail::panel>

<x-mail::button :url="$link">
Ir a la cuenta
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
