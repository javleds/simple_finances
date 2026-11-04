# Límites arquitectónicos

## Paquete compartido

`packages/finance-domain` depende de Laravel, pero no de `apps/finance`. Contiene modelos, relaciones, casts, enums, policies y consultas de visibilidad con actor explícito. Importar un modelo no autoriza consultas: los consumidores deben aplicar las reglas compartidas antes de filtrar, paginar o agregar. Las credenciales de lectura del futuro consumidor serán otra barrera independiente.

Finance conserva autenticación JWT, validación de payloads, Actions, migraciones y efectos externos. Sus hooks locales conservan asignación de propietario y eventos existentes. Los callbacks se registran en `FinanceModelServiceProvider`; el paquete no los ejecuta. Los nombres antiguos de modelos/enums se resuelven con aliases locales para payloads persistidos y las notificaciones conservan `App\Models\User` como tipo morph.

## Backend y transporte

Laravel controla validación, autorización, persistencia, reglas financieras y efectos externos. Los controladores API consumen FormRequest, servicios y modelos existentes. La SPA consume sus contratos JSON; compartir repositorio no mueve autorización al navegador ni cambia JWT por cookies.

Los servicios de transacciones sincronizan asignaciones, ledger, recuperación técnica, saldos y reembolsos. Mantén estas operaciones en el backend y sus pruebas. El ledger de miembros es una tabla de dominio, no un registro auxiliar.

## Frontend

`resources/js/modules` organiza páginas, componentes, queries, repositorios, schemas y tipos por funcionalidad. Usa componentes compartidos antes de crear primitivas nuevas. Pinia conserva estado de cliente y TanStack Query conserva estado de servidor; invalida las consultas de cuentas, dashboard y transacciones después de mutaciones financieras.

Blade solo proporciona el HTML de entrada. La navegación pertenece a Vue Router con base `/`; los assets pertenecen a Vite. El frontend usa `/api` salvo override público explícito y no importa lógica PHP ni credenciales backend.

## Operación

PHP y Nginx comparten el checkout en `/workspace`, con aplicación en `/workspace/apps/finance`; Node solo construye. El montaje obliga a activar mantenimiento antes de actualizar Git. MySQL conserva nombres y volumen anteriores. Traefik atiende dominio principal y antiguo host API sin redirigir enlaces firmados o webhooks.

## Categorías

Laravel resuelve el catálogo efectivo y autoriza creación, selección, renombrado y eliminación. La conversión al compartir una cuenta y la reclasificación al eliminar son operaciones atómicas. Vue presenta búsqueda, creación dentro del formulario y administración; las categorías no alteran el ledger ni las reglas financieras. Consulta [el dominio de categorías](../domain/categories.md).

## Comprobantes de transacciones

La API recibe archivos individuales, verifica permisos y reserva capacidad; los jobs validan contenido y convierten imágenes. Vue mantiene selecciones locales, presenta estados y descarga mediante JWT. La transacción financiera se guarda antes de subir comprobantes: los fallos de archivos no repiten notificaciones ni mutaciones financieras. Los archivos nunca se exponen mediante el disco público. La galería reutiliza el WebP privado para miniatura, visor y descarga; los blobs pertenecen a la sesión visible y se liberan al terminarla. Consulta [comprobantes de transacciones](../domain/transaction-attachments.md).

## Asistente financiero

`apps/mcp` consume el paquete compartido y tiene credenciales MySQL de solo lectura. No importa Actions de Finance ni recalcula datos al consultar. `apps/agents` conserva conversaciones en PostgreSQL y obtiene información exclusivamente mediante herramientas MCP autorizadas; no recibe credenciales MySQL.

La SPA utiliza `/api/chat` de Finance. Finance deriva el actor de su autenticación y llama a FastAPI por Docker con una credencial de servicio. El actor no puede elegirse desde el navegador ni desde argumentos del modelo. La identidad explícita es un dato confiado entre servicios autenticados, no un mecanismo de autenticación por sí mismo. Ver [el contrato y plan](../specs/015-agente-financiero-chat-mcp.md) y [operación local](../operations/assistant.md).
