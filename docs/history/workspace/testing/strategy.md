# Estrategia de pruebas y validacion

> Documento histórico anterior a la integración de Vue en Laravel. Las rutas `api/` y `spa/` se refieren al antiguo workspace; las instrucciones vigentes están en `AGENTS.md` y `docs` de la raíz Laravel.

## Backend `api`

Frameworks observados:

- PHPUnit 11 configurado por `api/phpunit.xml`.
- Pest 3 instalado en `api/composer.json`.
- PHPStan disponible mediante script Composer `stan`.
- PHP-CS-Fixer disponible mediante scripts `format` y `format:check`.

Suites observadas:

- `api/tests/Unit`
- `api/tests/Feature/Api`
- `api/tests/Feature/Services`
- `api/tests/Feature/Migrations`

Comandos verificados como disponibles:

```bash
cd api
php artisan test
composer stan
composer format:check
composer format
composer pint
```

Notas:

- `api/phpunit.xml` usa SQLite en memoria para tests.
- Las pruebas de servicios cubren reglas relevantes de transacciones, cuentas compartidas, dashboard, subscripciones, invitaciones y cuentas virtuales.
- Las pruebas de migracion cubren normalizacion de datos historicos de ledger y transacciones pendientes.

Confianza: Observado en `api/phpunit.xml`, `api/composer.json`, `api/tests` y salida de `composer run-script --list`.

## Frontend `spa`

Frameworks observados:

- Vitest para pruebas unitarias.
- Vue Test Utils y jsdom.
- `vue-tsc` para type-check.
- ESLint, Oxlint y Prettier.
- Playwright instalado; scripts existentes capturan vistas/facilities mediante `scripts/playwright/captureFacilities.mjs`.

Comandos verificados como disponibles:

```bash
cd spa
npm run dev
npm run build
npm run type-check
npm run test:unit
npm run lint
npm run format
npm run pw:views:list
npm run pw:views
```

Notas:

- `npm run lint` y `npm run format` modifican archivos porque usan `--fix` y `--write`; usarlos solo cuando se quiere aplicar cambios.
- Para cambios frontend listos, la instruccion raiz exige al menos `npm run type-check`.
- Hay specs cerca de schemas, queries, repositorios y utilidades compartidas.

Confianza: Observado en `spa/package.json`, `spa/src` y salida de `npm run`.

## Gaps observados

- No se encontro CI en el raiz ni en `spa/.github`; solo existe `api/.github/copilot-instructions.md`.
- No se ejecutaron suites completas durante bootstrap porque la tarea fue documental y no modifica comportamiento.
