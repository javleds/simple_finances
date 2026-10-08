# Reporte de bootstrap

> Documento histórico anterior a la integración de Vue en Laravel. Las rutas `api/` y `spa/` se refieren al antiguo workspace; las instrucciones vigentes están en `AGENTS.md` y `docs` de la raíz Laravel.

## Stack detectado

- `api`: Laravel 12, PHP 8.2, Eloquent, PHPUnit/Pest, PHPStan, PHP-CS-Fixer, Vite para assets heredados.
- `spa`: Vue 3, TypeScript, Vite, Vue Router, Pinia, TanStack Query, Tailwind CSS 4, Vitest, vue-tsc, ESLint/Oxlint/Prettier.
- Persistencia: migraciones Laravel y modelos Eloquent.
- Integraciones: email mediante Laravel Notifications/Mail, MailerSend instalado, Telegram Bot, OpenAI.

Confianza: Observado.

## Archivos y areas inspeccionadas

- `AGENTS.md`
- `api/AGENTS.md`
- `spa/AGENTS.md`
- `api/README.md`
- `spa/README.md`
- `api/composer.json`
- `api/package.json`
- `api/phpunit.xml`
- `api/Makefile`
- `api/bootstrap/app.php`
- `api/routes/api.php`
- `api/routes/console.php`
- `api/app/Models`
- `api/app/Http/Controllers/Api`
- `api/app/Http/Requests/Api`
- `api/app/Services`
- `api/app/Policies`
- `api/app/Contracts`
- `api/app/Console`
- `api/database/migrations`
- `api/tests`
- `api/docs/shared-account-model.md`
- `spa/package.json`
- `spa/vite.config.ts`
- `spa/src/main.ts`
- `spa/src/router/index.ts`
- `spa/src/lib/api/apiClient.ts`
- `spa/src/modules`

## Documentacion creada

- `docs/architecture/overview.md`
- `docs/architecture/boundaries.md`
- `docs/domain/shared-accounts.md`
- `docs/testing/strategy.md`
- `docs/operations/local-development.md`
- `docs/decisions/README.md`
- `docs/bootstrap-report.md`

## Documentacion actualizada

- `AGENTS.md`

## Reglas de negocio observadas

- El modelo de cuentas compartidas distingue transacciones economicas, asignaciones de responsabilidad y ledger de miembros.
- `account_member_ledger_entries` es una tabla central de dominio para custodia, settlements y reembolsos.
- Nuevas escrituras de transacciones operativas deben usar estado `completed`.
- Las transacciones hijas historicas migradas se marcan con `legacy_migrated_at` y deben excluirse de consultas operativas.
- Las transferencias entre miembros registran movimientos de settlement; la custodia se ajusta con operaciones explicitas separadas.
- La API protege rutas autenticadas con JWT Bearer y aplica autorizacion explicita de propiedad/membresia en servicios/controladores.
- La SPA debe consumir el API mediante repositorios/composables y usar componentes compartidos existentes antes de crear UI nueva.

## Reglas inferidas que requieren confirmacion

- La estrategia operativa final de despliegue no queda completamente documentada. Hay Dockerfiles/compose, pero desarrollo local favorece ejecucion en host.
- La migracion desde Filament a SPA esta en curso; Filament sigue presente/historico en el API, pero nuevas UI deben ir a `spa`.
- Las reglas de cuentas de credito estan incompletas segun `api/AGENTS.md`; cualquier cambio en esa zona debe partir de tests y aclaracion funcional.

## Incertidumbres

- No se encontro un repositorio Git en el directorio raiz; `api` y `spa` tienen estados Git separados o al menos metadatos separados.
- No se encontro CI de validacion automatica en el raiz ni en `spa`.
- No se verifico una instalacion desde cero.

## Documentacion potencialmente desactualizada

- `api/README.md` describe el proyecto como Laravel + Filament y contiene requisitos PHP 8.1+, mientras `api/composer.json` exige PHP `^8.2` y la direccion raiz indica que nuevas UI deben construirse en `spa`.
- `spa/README.md` conserva texto de plantilla Vite y no describe la aplicacion financiera.
- `api/AGENTS.md` contiene lineamientos historicos de Filament; siguen siendo utiles para codigo backend heredado, pero la instruccion raiz actual prioriza SPA para nuevas UI.

## Comandos verificados

```bash
cd api
composer run-script --list
php artisan list --format=txt
```

```bash
cd spa
npm run
```

Tambien se verifico que `api` tiene scripts NPM `dev` y `build`.

## Recomendaciones

- Actualizar `api/README.md` y `spa/README.md` en una tarea separada para reflejar la arquitectura actual.
- Definir una guia de despliegue real si Docker Compose es el camino productivo.
- Agregar CI que ejecute `api` tests/static analysis y `spa` type-check/tests.
- Mantener `api/docs/shared-account-model.md` como fuente larga de dominio para cuentas compartidas y enlazarla desde cambios futuros.
