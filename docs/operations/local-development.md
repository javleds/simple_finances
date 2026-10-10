# Desarrollo local

## Preparación

Trabaja desde la raíz del repositorio Laravel (`api` dentro del workspace anterior). Requiere PHP 8.4 (requerido por las dependencias del lockfile), Composer, Node 24 y npm. Habilita las extensiones requeridas por Composer y el driver de la base elegida; las pruebas necesitan SQLite/PDO SQLite.

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
```

Configura `DB_*` para una base local y crea la base antes de `php artisan migrate`. Para SQLite configura `DB_CONNECTION=sqlite`, crea `database/database.sqlite` y usa su ruta absoluta en `DB_DATABASE`. No ejecutes migraciones sobre una base compartida de producción.

`.npmrc` conserva `legacy-peer-deps=true` por incompatibilidades de peer dependencies heredadas de la SPA. Usa el lockfile con `npm ci`; esta integración no corrige ni actualiza esas dependencias.

## Un único comando

```bash
composer run dev
```

Laravel escucha en `127.0.0.1:8000`; Vite en `127.0.0.1:5173` sirve assets y HMR. Abre la aplicación en `http://127.0.0.1:8000`, incluida una ruta profunda como `/admin/accounts`. Ambos puertos son estrictos: si uno está ocupado, el comando termina. Composer también inicia `php artisan queue:listen --sleep=1 --tries=5 --timeout=60`. `Ctrl+C` o la salida de uno de los procesos detiene los demás.

El cliente usa `/api` por defecto. `VITE_API_BASE_URL` permite un override público para integraciones. Mantén `APP_URL`, `SPA_URL` y `FRONTEND_URL` en la URL local. Las variables `VITE_*` se incorporan al navegador y nunca deben contener secretos.

Usa `QUEUE_CONNECTION=database` para WhatsApp y aplica las migraciones antes de iniciar la aplicación. La cola y `webhook_receipts` deben usar la misma conexión de base de datos; `sync` y Redis no satisfacen el contrato de recepción durable implementado. El scheduler se ejecuta por separado cuando se necesita. `composer run dev` no instala paquetes ni migra datos.

## WhatsApp y PrimeVue

Configura `VITE_PRIMEVUE_LICENSE_KEY` con la licencia PrimeVue; Vite la incorpora al frontend durante el arranque o el build. Las credenciales `WA_*` permanecen en Laravel. Reinicia Vite al cambiar la licencia y limpia la caché de configuración Laravel si cambias credenciales previamente cacheadas.

Para verificar el webhook desde Meta, reenvía el puerto `8000` mediante un túnel HTTPS público y registra `https://<túnel>/api/whatsapp/webhook`. El token de verificación debe coincidir con `WA_API_TOKEN`. Mantén el servidor y el túnel encendidos; para procesar eventos también debe ejecutarse el worker. El puerto de Vite no es el endpoint del webhook.

El GET debe responder solo el challenge, con `Content-Type: text/plain` y `Content-Length` correcto. HTML de Debugbar rompe la verificación; la longitud explícita evita que el túnel probado mantenga abierta la respuesta. La publicación de la app en Meta y el acceso público al backend son requisitos distintos: el panel puede restringir una app sin publicar a eventos de prueba.

Consulta [WhatsApp](../integrations/whatsapp.md) para variables, plantilla, limitaciones de la cuenta de prueba y recuperación de jobs.

## Build y comprobaciones

Vite 8, `laravel-vite-plugin` 3 y Tailwind 4 compilan `resources/js/main.ts` a `public/build`; Blade selecciona HMR o manifest automáticamente.

```bash
npm run type-check
npm run test:unit -- --run
npm run build
php artisan test
composer format:check
```

Para verificar producción local, detén Vite y comprueba que no exista `public/hot`; inicia solo `php artisan serve --host=127.0.0.1 --port=8000` y abre rutas profundas. `npm run preview` no representa la aplicación Laravel integrada.
