<x-mail::message>
# Hola, {{ $invite->user->name }}.

{{ $invite->email }} ha {{ $invite->status->getActionLabel() }} tu invitación a la cuenta compartida {{ $account->name }}.

<x-mail::button :url="''">
Ir a mi cuenta
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
