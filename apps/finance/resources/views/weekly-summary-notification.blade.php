<x-mail::message>
# ¡Hola, {{ $user->name }}!

Preparamos un resumen de las transacciones de tus cuentas, esperamos que te sea de utilidad.

<small>Si deseas dejar de recibir notificaciones de este tipo, puedes hacerlo <a href="#">aquí</a>.</small>

Con cariño,<br>
{{ config('app.name') }}
</x-mail::message>
