<x-mail::message>
# Hola, {{ $user->name }}.

Se registraron {{ $globalSummary['movements_count'] }} movimientos en {{ $globalSummary['accounts_count'] }} cuentas compartidas.

## Resumen general

| Cuentas | Movimientos | Ingresos | Egresos | Neto |
| ---: | ---: | ---: | ---: | ---: |
| {{ $globalSummary['accounts_count'] }} | {{ $globalSummary['movements_count'] }} | {{ as_money($globalSummary['income_total']) }} | {{ as_money($globalSummary['outcome_total']) }} | {{ as_money($globalSummary['net_total']) }} |

## Estado por cuenta

| Cuenta | Balance actual | Por pagar | Por recibir | Neto de movimientos |
| --- | ---: | ---: | ---: | ---: |
@foreach ($accountsSummary as $summary)
| {{ $summary['account_name'] }} | {{ as_money($summary['balance']) }} | {{ as_money($summary['por_pagar']) }} | {{ as_money($summary['por_recibir']) }} | {{ as_money($summary['net_total']) }} |
@endforeach

## Movimientos

@foreach ($itemsByAccount as $group)
### {{ $group['account']->name }}

| Acción | Tipo | Concepto | Monto | Fecha | Registrado por |
| --- | --- | --- | ---: | --- | --- |
@foreach ($group['items'] as $item)
| {{ ucfirst($item->action->getLabel()) }} | {{ $item->action === \App\Enums\SharedTransactionNotificationAction::Settled ? 'Reembolso' : $item->type->getLabel() }} | {{ $item->concept }} | {{ as_money($item->amount) }} | {{ $item->scheduled_at->translatedFormat('M d, Y') }} | {{ $item->modifier?->name ?? 'Usuario no disponible' }} |
@endforeach

@endforeach

<x-mail::button :url="$link">
Ver cuentas
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
