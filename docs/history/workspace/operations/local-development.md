# Desarrollo local

> Documento histórico anterior a la integración de Vue en Laravel. Las rutas `api/` y `spa/` se refieren al antiguo workspace; las instrucciones vigentes están en `AGENTS.md` y `docs` de la raíz Laravel.

## Requisitos observados

Backend `api`:

- PHP `^8.2`.
- Composer.
- Node/NPM para assets heredados de Laravel/Vite.
- Base de datos configurable por Laravel; `api/phpunit.xml` usa SQLite en memoria para tests.

Frontend `spa`:

- Node `^20.19.0 || >=22.12.0`.
- NPM, observado por `package-lock.json`.

Confianza: Observado en `api/composer.json`, `api/package-lock.json`, `api/phpunit.xml`, `spa/package.json` y `spa/package-lock.json`.

## Backend

Desde `api`:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Comandos de validacion disponibles:

```bash
php artisan test
composer stan
composer format:check
```

Notas:

- No ejecutar migraciones contra entornos desconocidos sin confirmar `DB_*`.
- `api/Makefile` usa `docker compose -f docker-compose.prod.yml` para comandos de tooling, pero las instrucciones raiz indican ejecutar backend en host para desarrollo local.
- `app:post-deploy --migrate` corre migraciones y cachea config/rutas/eventos; reservarlo para contexto de despliegue confirmado.

## Frontend

Desde `spa`:

```bash
npm install
cp .env.example .env
npm run dev
```

Variables necesarias observadas:

```bash
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

`VITE_API_BASE_URL` es obligatoria porque `spa/src/lib/api/apiClient.ts` lanza error si falta.

Comandos de validacion disponibles:

```bash
npm run type-check
npm run test:unit
npm run build
```

Comandos que modifican archivos:

```bash
npm run lint
npm run format
```

## Integraciones locales

Variables de entorno observadas en `api/config/services.php` y `api/README.md`:

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_WEBHOOK_URL`
- `TELEGRAM_VERIFICATION_CODE_EXPIRATION_MINUTES`
- `OPENAI_API_TOKEN`
- `OPENAI_DEFAULT_MODEL`
- `OPENAI_VISION_MODEL`
- `OPENAI_AUDIO_MODEL`
- `OPENAI_MAX_TOKENS`
- `OPENAI_TEMPERATURE`

No colocar secretos reales en documentacion ni commits.
