# Limites arquitectonicos

> Documento histórico anterior a la integración de Vue en Laravel. Las rutas `api/` y `spa/` se refieren al antiguo workspace; las instrucciones vigentes están en `AGENTS.md` y `docs` de la raíz Laravel.

## Repositorio compuesto

Responsabilidad: coordinar dos aplicaciones separadas, `api` y `spa`, que evolucionan juntas cuando cambia un contrato de producto.

Interacciones observadas:

- `spa` consume endpoints de `api` mediante `VITE_API_BASE_URL`.
- Las instrucciones raiz piden revisar ambos proyectos antes de asumir que un cambio es solo backend o solo frontend.

Rutas relevantes: `AGENTS.md`, `api/routes/api.php`, `spa/src/lib/api/apiClient.ts`.

Confianza: Observado.

## API HTTP

Responsabilidad: exponer contratos JSON para autenticacion, perfil, dashboard, cuentas, transacciones, metas financieras, subscripciones, notificaciones, Telegram y cuentas virtuales.

Dependencias permitidas observadas:

- Controladores dependen de `FormRequest`, modelos Eloquent y servicios de aplicacion.
- Middleware `api.auth` protege rutas autenticadas.
- Servicios `AuthorizeAccountAccess` y `AuthorizeUserOwnedResource` centralizan verificaciones repetidas de propiedad o membresia.

Rutas relevantes: `api/routes/api.php`, `api/app/Http/Controllers/Api`, `api/app/Http/Requests/Api`, `api/app/Services/Api`.

Confianza: Observado.

## Dominio y orquestacion backend

Responsabilidad: aplicar reglas de negocio y efectos secundarios fuera de controladores cuando la operacion tiene varios pasos.

Interacciones observadas:

- `TransactionCreator`, `TransactionUpdater` y `TransactionRemover` escriben la transaccion economica y sincronizan asignaciones, ledger de miembros, balance y efectos secundarios.
- Servicios de cuentas compartidas construyen resumen de custodia, reembolsos, timeline y reparaciones.
- Servicios de dashboard construyen agregados consumidos por la SPA.

Rutas relevantes: `api/app/Services/Transaction`, `api/app/Services/Accounts`, `api/app/Services/Dashboard`, `api/docs/shared-account-model.md`.

Confianza: Observado.

## Persistencia

Responsabilidad: mantener datos de finanzas personales y compartidas mediante modelos Eloquent y migraciones Laravel.

Interacciones observadas:

- Modelos Eloquent definen relaciones y casts.
- Migraciones normalizan datos historicos de transacciones compartidas hacia `transaction_allocations` y `account_member_ledger_entries`.
- Tests de migracion cubren normalizaciones de ledger y transacciones pendientes.

Rutas relevantes: `api/app/Models`, `api/database/migrations`, `api/tests/Feature/Migrations`.

Confianza: Observado.

## Integraciones backend

Responsabilidad: encapsular servicios externos de Telegram, OpenAI y correo.

Interacciones observadas:

- Contratos bajo `api/app/Contracts` abstraen Telegram/OpenAI y procesamiento de acciones.
- Implementaciones dummy existen para Telegram/OpenAI.
- Notifications de Laravel envian correos de invitaciones, resumen semanal y cambios en transacciones compartidas.

Rutas relevantes: `api/app/Contracts`, `api/app/Services/Telegram`, `api/app/Services/OpenAI`, `api/app/Notifications`, `api/config/services.php`.

Confianza: Observado.

## Frontend modular

Responsabilidad: organizar cada area funcional de la SPA en paginas, componentes, composables, repositorios, queries, schemas y tipos.

Interacciones observadas:

- `spa/src/router/index.ts` compone rutas de auth y admin; las rutas admin agrupan modulos internos.
- Repositorios encapsulan transporte HTTP.
- Queries/composables encapsulan estado de servidor y acciones de UI.
- Schemas validan o parsean payloads de API.

Rutas relevantes: `spa/src/modules`, `spa/src/router/index.ts`, `spa/src/lib/api/apiClient.ts`.

Confianza: Observado.

## Componentes compartidos SPA

Responsabilidad: proveer primitivas visuales reutilizables y consistentes.

Interacciones observadas:

- `spa/AGENTS.md` lista los componentes compartidos actuales y exige reutilizarlos antes de crear UI nueva.
- Los componentes se exportan desde `spa/src/modules/shared/components/index.ts`.

Rutas relevantes: `spa/AGENTS.md`, `spa/src/modules/shared/components`.

Confianza: Observado.

## Incertidumbres y excepciones

- No hay ADRs existentes en el raiz; no se debe presentar la arquitectura actual como una decision formal retroactiva.
- `api/AGENTS.md` conserva lineamientos historicos de Filament. La instruccion raiz vigente dirige nuevas UI a `spa`; cuando haya conflicto, tratar Filament como legado salvo solicitud explicita.
