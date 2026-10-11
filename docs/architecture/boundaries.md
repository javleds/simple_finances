# Límites arquitectónicos

## Backend y transporte

Laravel controla validación, autorización, persistencia, reglas financieras y efectos externos. Los controladores API consumen FormRequest, servicios y modelos existentes. La SPA consume sus contratos JSON; compartir repositorio no mueve autorización al navegador ni cambia JWT por cookies.

Los servicios de transacciones sincronizan asignaciones, ledger, recuperación técnica, saldos y reembolsos. Mantén estas operaciones en el backend y sus pruebas. El ledger de miembros es una tabla de dominio, no un registro auxiliar.

## Frontend

`resources/js/modules` organiza páginas, componentes, queries, repositorios, schemas y tipos por funcionalidad. Usa componentes compartidos antes de crear primitivas nuevas. Pinia conserva estado de cliente y TanStack Query conserva estado de servidor; invalida las consultas de cuentas, dashboard y transacciones después de mutaciones financieras.

Blade solo proporciona el HTML de entrada. La navegación pertenece a Vue Router con base `/`; los assets pertenecen a Vite. El frontend usa `/api` salvo override público explícito y no importa lógica PHP ni credenciales backend.

## Operación

PHP y Nginx comparten el checkout y assets; Node solo construye. El montaje obliga a activar mantenimiento antes de actualizar Git. MySQL conserva nombres y volumen anteriores. Traefik atiende dominio principal y antiguo host API sin redirigir enlaces firmados o webhooks.

## Categorías

Laravel resuelve el catálogo efectivo y autoriza creación, selección, renombrado y eliminación. La conversión al compartir una cuenta y la reclasificación al eliminar son operaciones atómicas. Vue presenta búsqueda, creación dentro del formulario y administración; las categorías no alteran el ledger ni las reglas financieras. Consulta [el dominio de categorías](../domain/categories.md).
