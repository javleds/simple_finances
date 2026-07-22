<x-mail::message>
# Hola, {{ $user->name }}.

Se registraron **{{ $globalSummary['movements_count'] }} movimientos** en **{{ $globalSummary['accounts_count'] }} cuentas compartidas**.

## Resumen general

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 18px 0 26px;">
<tr>
<td style="padding: 12px 10px; border: 1px solid #e8e5ef; border-radius: 8px; background: #faf9fc;">
<div style="font-size: 12px; color: #6b6675; margin-bottom: 4px;">Cuentas</div>
<div style="font-size: 20px; font-weight: 700; color: #24212b;">{{ $globalSummary['accounts_count'] }}</div>
</td>
<td width="10"></td>
<td style="padding: 12px 10px; border: 1px solid #e8e5ef; border-radius: 8px; background: #faf9fc;">
<div style="font-size: 12px; color: #6b6675; margin-bottom: 4px;">Movimientos</div>
<div style="font-size: 20px; font-weight: 700; color: #24212b;">{{ $globalSummary['movements_count'] }}</div>
</td>
<td width="10"></td>
<td style="padding: 12px 10px; border: 1px solid #e8e5ef; border-radius: 8px; background: #faf9fc;">
<div style="font-size: 12px; color: #6b6675; margin-bottom: 4px;">Neto</div>
<div style="font-size: 20px; font-weight: 700; color: #24212b;">{{ as_money($globalSummary['net_total']) }}</div>
</td>
</tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 28px; border-collapse: collapse;">
<tr>
<td style="padding: 12px 14px; background: #f6f4f9; color: #5f5a6a; font-size: 13px; font-weight: 700; border-bottom: 1px solid #e4e0ea;">Ingresos</td>
<td align="right" style="padding: 12px 14px; background: #f6f4f9; color: #24212b; font-size: 14px; font-weight: 700; border-bottom: 1px solid #e4e0ea;">{{ as_money($globalSummary['income_total']) }}</td>
</tr>
<tr>
<td style="padding: 12px 14px; color: #5f5a6a; font-size: 13px; font-weight: 700; border-bottom: 1px solid #eeeaf2;">Egresos</td>
<td align="right" style="padding: 12px 14px; color: #24212b; font-size: 14px; font-weight: 700; border-bottom: 1px solid #eeeaf2;">{{ as_money($globalSummary['outcome_total']) }}</td>
</tr>
<tr>
<td style="padding: 12px 14px; color: #5f5a6a; font-size: 13px; font-weight: 700;">Neto de movimientos</td>
<td align="right" style="padding: 12px 14px; color: #24212b; font-size: 14px; font-weight: 700;">{{ as_money($globalSummary['net_total']) }}</td>
</tr>
</table>

## Estado por cuenta

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 12px 0 30px; border-collapse: collapse;">
<tr>
<th align="left" style="padding: 10px 8px; border-bottom: 2px solid #ded9e7; color: #5f5a6a; font-size: 12px;">Cuenta</th>
<th align="right" style="padding: 10px 8px; border-bottom: 2px solid #ded9e7; color: #5f5a6a; font-size: 12px;">Balance</th>
<th align="right" style="padding: 10px 8px; border-bottom: 2px solid #ded9e7; color: #5f5a6a; font-size: 12px;">Por pagar</th>
<th align="right" style="padding: 10px 8px; border-bottom: 2px solid #ded9e7; color: #5f5a6a; font-size: 12px;">Por recibir</th>
</tr>
@foreach ($accountsSummary as $summary)
<tr>
<td style="padding: 12px 8px; border-bottom: 1px solid #eeeaf2; color: #24212b; font-size: 14px; font-weight: 700;">{{ $summary['account_name'] }}</td>
<td align="right" style="padding: 12px 8px; border-bottom: 1px solid #eeeaf2; color: #24212b; font-size: 14px;">{{ as_money($summary['balance']) }}</td>
<td align="right" style="padding: 12px 8px; border-bottom: 1px solid #eeeaf2; color: #24212b; font-size: 14px;">{{ as_money($summary['por_pagar']) }}</td>
<td align="right" style="padding: 12px 8px; border-bottom: 1px solid #eeeaf2; color: #24212b; font-size: 14px;">{{ as_money($summary['por_recibir']) }}</td>
</tr>
@endforeach
</table>

## Movimientos

@foreach ($itemsByAccount as $group)
<div style="margin: 22px 0 10px;">
<div style="font-size: 18px; font-weight: 700; color: #24212b;">{{ $group['account']->name }}</div>
<div style="font-size: 13px; color: #6b6675; margin-top: 4px;">
{{ $group['summary']['movements_count'] }} movimientos · Neto {{ as_money($group['summary']['net_total']) }}
</div>
</div>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 26px; border-collapse: collapse;">
@foreach ($group['items'] as $item)
<tr>
<td style="padding: 13px 0; border-bottom: 1px solid #eeeaf2;">
<div style="font-size: 14px; font-weight: 700; color: #24212b; line-height: 1.4;">{{ $item->concept }}</div>
<div style="font-size: 12px; color: #6b6675; margin-top: 4px; line-height: 1.4;">
{{ ucfirst($item->action->getLabel()) }} · {{ $item->action === \App\Enums\SharedTransactionNotificationAction::Settled ? 'Reembolso' : $item->type->getLabel() }} · {{ $item->scheduled_at->translatedFormat('M d, Y') }}
</div>
<div style="font-size: 12px; color: #8a8494; margin-top: 2px;">Registrado por {{ $item->modifier?->name ?? 'Usuario no disponible' }}</div>
</td>
<td align="right" style="padding: 13px 0 13px 12px; border-bottom: 1px solid #eeeaf2; font-size: 15px; font-weight: 700; color: #24212b; white-space: nowrap;">
{{ as_money($item->amount) }}
</td>
</tr>
@endforeach
</table>

@endforeach

<x-mail::button :url="$link">
Ver cuentas
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
