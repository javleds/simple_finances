<x-mail::message>
# ¡Hola!

{{ $invite->user->name }} quiere invitarte a administrar la cuenta {{ $invite->account->name }} en la aplicación de {{ config('app.name') }},

<x-mail::button :url="$link">
Aceptar invitación
</x-mail::button>

<small>Si crees que este mensaje es un error, no es necesario realizar ninguna acción.</small>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
