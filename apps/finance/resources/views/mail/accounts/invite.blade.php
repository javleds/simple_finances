<x-mail::message>
# ¡Hola!

{{ $invite->user->name }} quiere invitarte a administrar la cuenta {{ $invite->account->name }}
@if ($invite->hasPercentageAssigned())
    con un porcentaje del {{ $invite->percentage }}% en los egresos a traves de
@else
    en
@endif
la aplicación de {{ config('app.name') }},

<x-mail::button :url="$link">
Aceptar invitación
</x-mail::button>

<small>Si crees que este mensaje es un error, no es necesario realizar ninguna acción.</small>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
