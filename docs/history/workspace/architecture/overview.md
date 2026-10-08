# Resumen de arquitectura

> Documento histórico anterior a la integración de Vue en Laravel. Las rutas `api/` y `spa/` se refieren al antiguo workspace; las instrucciones vigentes están en `AGENTS.md` y `docs` de la raíz Laravel.

## Proposito

Fin SI es una aplicacion de finanzas personales compuesta por dos proyectos hermanos:

- `api`: backend Laravel que contiene el modelo de dominio, persistencia, endpoints HTTP, autenticacion, integraciones y procesos programados.
- `spa`: aplicacion Vue 3 que consume la API y es el frontend objetivo para nuevas funcionalidades de usuario.

Confianza: Observado en `AGENTS.md`, `api/composer.json`, `api/routes/api.php`, `spa/package.json`, `spa/src/main.ts` y `spa/src/router/index.ts`.

## Aplicaciones

### `api`

Backend Laravel 12 con PHP 8.2. Expone rutas bajo `routes/api.php`, usa controladores en `app/Http/Controllers/Api`, validacion con `FormRequest`, modelos Eloquent en `app/Models` y servicios de aplicacion/dominio en `app/Services`.

El API mantiene tambien la interfaz Filament heredada por sus dependencias y recursos historicos, pero las instrucciones del repositorio indican que las nuevas experiencias de usuario deben implementarse en `spa`.

Confianza: Observado en `api/composer.json`, `api/routes/api.php`, `api/app/Http/Controllers/Api`, `api/app/Services` y `AGENTS.md`.

### `spa`

Frontend Vue 3 + TypeScript + Vite. Usa Vue Router, Pinia para estado de cliente, TanStack Query para estado de servidor, Tailwind CSS 4, Heroicons y librerias instaladas para formularios, selects, date pickers y graficas.

Confianza: Observado en `spa/package.json`, `spa/vite.config.ts`, `spa/src/main.ts`, `spa/src/router/index.ts` y `spa/AGENTS.md`.

## Flujo principal

1. La SPA carga desde `spa/src/main.ts`, instala Pinia, Vue Query y el router.
2. El router aplica guardas de autenticacion y verificacion de email antes de entrar a rutas `admin.*`.
3. Los modulos de la SPA organizan pantallas, componentes, composables, queries, repositorios, schemas y tipos por dominio funcional.
4. Los repositorios de la SPA llaman al API mediante `spa/src/lib/api/apiClient.ts`, que exige `VITE_API_BASE_URL` y agrega token Bearer cuando existe.
5. Laravel valida peticiones con `FormRequest`, autoriza acceso con middleware/servicios explicitos y orquesta cambios en servicios bajo `app/Services`.
6. Las transacciones, cuentas, cuentas compartidas, metas, subscripciones, notificaciones e invitaciones persisten en tablas Eloquent y migraciones de `api/database/migrations`.

Confianza: Observado.

## Persistencia

El backend usa Eloquent y migraciones Laravel. Las tablas centrales observadas incluyen:

- `accounts`
- `transactions`
- `transaction_allocations`
- `account_member_ledger_entries`
- `account_user`
- `account_invites`
- `financial_goals`
- `subscriptions`
- `subscription_payments`
- `notification_types`
- `notification_setups`
- `shared_transaction_notification_batches`
- `shared_transaction_notification_items`
- `account_balance_snapshots`
- `telegram_verification_codes`

Confianza: Observado en `api/database/migrations` y `api/app/Models`.

## Integraciones externas

- Email: Laravel Notifications y configuracion de mail; `mailersend/laravel-driver` esta instalado.
- Telegram: webhook y comandos `app:telegram:*`; servicios bajo `app/Services/Telegram`.
- OpenAI: cliente `openai-php/client`; servicios y prompts bajo `app/Services/OpenAI`.

Confianza: Observado en `api/composer.json`, `api/config/services.php`, `api/app/Services/Telegram`, `api/app/Services/OpenAI` y `api/app/Notifications`.

## Procesos programados

`api/routes/console.php` programa:

- actualizacion automatica de cuentas diariamente a las 00:01;
- actualizacion diaria de subscripciones a las 00:30;
- resumen semanal los domingos a las 08:00;
- procesamiento de lotes de notificaciones de transacciones compartidas cada minuto.

Confianza: Observado.

## Seguridad y autorizacion

La API usa un middleware `api.auth` que valida tokens JWT Bearer generados por `App\Services\Auth\JwtTokenService`. Varias rutas usan servicios explicitos de autorizacion como `AuthorizeAccountAccess` y `AuthorizeUserOwnedResource`; tambien existen policies para `Account`, `Transaction` y `FinancialGoal`.

Confianza: Observado en `api/bootstrap/app.php`, `api/app/Http/Middleware/AuthenticateApiToken.php`, `api/app/Services/Auth/JwtTokenService.php`, `api/app/Services/Api` y `api/app/Policies`.

## Incertidumbres

- El despliegue real no queda completamente establecido por el repo raiz. Existen Dockerfiles y compose files en ambos proyectos, pero las instrucciones locales favorecen ejecutar backend y frontend en host para desarrollo.
- La documentacion `api/README.md` conserva partes de la etapa Filament y puede estar parcialmente desactualizada frente a la direccion actual SPA + API.
