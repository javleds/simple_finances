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

Abre `http://127.0.0.1:8000`. El comando supervisa PHP, Vite y el worker de colas; `Ctrl+C` detiene los tres procesos. No instala dependencias, ejecuta migraciones ni inicia el scheduler.

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

## WhatsApp

Configuración permite vincular un teléfono mexicano al usuario autenticado mediante un código enviado por WhatsApp y desvincularlo posteriormente. La integración recibe webhooks firmados y guarda cada recepción junto con su job antes de responder `200`; el worker procesa las recepciones de forma asíncrona.

El acceso a la SPA sigue usando correo, contraseña y JWT. El código de WhatsApp verifica la vinculación del teléfono; no inicia sesión. La prueba real de vinculación queda pendiente de disponer de una plantilla de autenticación aprobada. Consulta [configuración, contratos y pruebas de WhatsApp](docs/integrations/whatsapp.md).

Consulta [desarrollo local](docs/operations/local-development.md), [despliegue](docs/operations/deployment.md), [arquitectura](docs/architecture/overview.md) y [pruebas](docs/testing/strategy.md). WhatsApp, Telegram, OpenAI y correo se configuran desde Laravel; no publiques tokens en variables `VITE_*`.
