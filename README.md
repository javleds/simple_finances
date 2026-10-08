# Fin SI

Aplicación de finanzas personales y cuentas compartidas en un único repositorio Laravel 12. Laravel sirve la SPA Vue 3 y la API HTTP; Vue conserva TypeScript, Vue Router, Pinia, TanStack Query y Tailwind 4.

## Inicio rápido

Requiere PHP 8.4 o superior, Composer, Node 24 y npm. Desde la raíz de este repositorio:

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
```

Configura una base local en `.env`, créala y ejecuta `php artisan migrate`. Después:

```bash
composer run dev
```

Abre `http://127.0.0.1:8000`. El comando supervisa PHP y Vite; `Ctrl+C` detiene ambos. No instala dependencias, ejecuta migraciones ni inicia workers o scheduler.

## Organización y validación

- `resources/js`: frontend modular y pruebas Vue.
- `app`, `routes/api.php`, `database`: negocio, contratos y persistencia Laravel.
- `resources/views/spa.blade.php`: entrada HTML con Vite.
- `docs/api.json`: contrato HTTP, sin enlaces a otro checkout.

```bash
npm run type-check
npm run test:unit -- --run
npm run build
php artisan test
composer format:check
```

Consulta [desarrollo local](docs/operations/local-development.md), [despliegue](docs/operations/deployment.md), [arquitectura](docs/architecture/overview.md) y [pruebas](docs/testing/strategy.md). Telegram, OpenAI y correo siguen configurándose desde Laravel; no publiques tokens en variables `VITE_*`.
