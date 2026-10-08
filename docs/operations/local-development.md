# Desarrollo local

## Preparación

Trabaja desde la raíz del repositorio Laravel (`api` dentro del workspace anterior). Requiere PHP `^8.2`, Composer, Node 24 y npm. Habilita las extensiones requeridas por Composer y el driver de la base elegida; las pruebas necesitan SQLite/PDO SQLite.

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

Laravel escucha en `127.0.0.1:8000`; Vite en `127.0.0.1:5173` sirve assets y HMR. Abre la aplicación en `http://127.0.0.1:8000`, incluida una ruta profunda como `/admin/accounts`. Ambos puertos son estrictos: si uno está ocupado, el comando termina. `Ctrl+C` o la salida de un proceso detiene el otro.

El cliente usa `/api` por defecto. `VITE_API_BASE_URL` permite un override público para integraciones. Mantén `APP_URL`, `SPA_URL` y `FRONTEND_URL` en la URL local. Las variables `VITE_*` se incorporan al navegador y nunca deben contener secretos.

Workers y scheduler se ejecutan por separado cuando se necesitan. `composer run dev` no instala paquetes ni migra datos.

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
