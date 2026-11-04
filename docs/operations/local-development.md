# Desarrollo local

## Preparación

El repositorio es un monorepo. Ejecuta comandos Laravel, Composer, npm y Vite desde `apps/finance`; Compose y `deploy.sh` permanecen en la raíz Git. Requiere PHP 8.4 (requerido por las dependencias del lockfile), Composer, Node 24 y npm. Habilita las extensiones requeridas por Composer y el driver de la base elegida; las pruebas necesitan SQLite/PDO SQLite.

```bash
cd apps/finance
cp .env.example .env
composer install
npm ci
php artisan key:generate
```

Configura `DB_*` para una base local y crea la base antes de `php artisan migrate`. Para SQLite configura `DB_CONNECTION=sqlite`, crea `database/database.sqlite` y usa su ruta absoluta en `DB_DATABASE`. No ejecutes migraciones sobre una base compartida de producción.

`.npmrc` conserva `legacy-peer-deps=true` por incompatibilidades de peer dependencies heredadas de la SPA. Usa el lockfile con `npm ci`; esta integración no corrige ni actualiza esas dependencias.

## Un único comando desde la raíz

```bash
make dev
make dev-list
make dev SERVICES="finance"
```

Requiere Node 24 (`.nvmrc` en raíz), Make y Bash. `make dev` inicia todas las aplicaciones con `apps/<nombre>/scripts/dev.sh`; actualmente solo Finance. La selección acepta varios nombres separados por espacios, valida todos antes de arrancar y elimina duplicados. `Ctrl+C` detiene todos los grupos de procesos; si un servicio termina, el supervisor detiene los demás y conserva su código de salida. Tras cinco segundos fuerza la terminación de procesos que no responden. No instala dependencias, inicia bases de datos ni ejecuta migraciones.

Cuando se implementen MCP y agentes, cada aplicación añadirá su propio `scripts/dev.sh`, ejecutado con su carpeta como directorio de trabajo. Entonces podrán seleccionarse, por ejemplo, con `make dev SERVICES="finance mcp agents"`. Hoy esos nombres se rechazan porque todavía no existen. Cada servicio debe permanecer en primer plano y configurar sus propios puertos.

El arranque específico de Finance sigue disponible desde `apps/finance`:

```bash
composer run dev
```

Las pruebas aisladas del supervisor se ejecutan desde la raíz con `make dev-test`.

Laravel escucha en `127.0.0.1:8000`; Vite en `127.0.0.1:5173` sirve assets y HMR. Abre la aplicación en `http://127.0.0.1:8000`, incluida una ruta profunda como `/admin/accounts`. Ambos puertos son estrictos: si uno está ocupado, el comando termina. Composer también inicia `php artisan queue:listen --queue=default,attachments --sleep=1 --tries=5 --timeout=60`. `Ctrl+C` o la salida de uno de los procesos detiene los demás.

`composer run dev` invoca `scripts/dev.sh`. En macOS con Homebrew selecciona el PHP de `brew --prefix php` para servidor y colas; en otros entornos usa el PHP del `PATH`. Antes de iniciar comprueba Imagick y soporte JPEG, PNG, WebP y HEIC, e imprime el binario elegido. Si falta alguna dependencia, no inicia los procesos.

El cliente usa `/api` por defecto. `VITE_API_BASE_URL` permite un override público para integraciones. Mantén `APP_URL`, `SPA_URL` y `FRONTEND_URL` en la URL local. Las variables `VITE_*` se incorporan al navegador y nunca deben contener secretos.

Usa `QUEUE_CONNECTION=database` para WhatsApp y aplica las migraciones antes de iniciar la aplicación. La cola y `webhook_receipts` deben usar la misma conexión de base de datos; `sync` y Redis no satisfacen el contrato de recepción durable implementado. El scheduler se ejecuta por separado cuando se necesita. `composer run dev` no instala paquetes ni migra datos.

## Assets de desarrollo y compilados

El CSS principal es una entrada independiente de Vite y Blade lo carga en el `<head>` para que el HTML inicial tenga estilos sin esperar al módulo Vue. La preferencia `theme-mode` se aplica antes de cargar los assets; Pinia mantiene el control del tema después del arranque. Conservar ambas entradas (`main.css` y `main.ts`) en Blade y en la configuración de Vite.

Si la aplicación ya está levantada, reutiliza el servidor existente. Comprueba qué entrada carga el navegador: `/@vite/client` indica HMR; `/build/assets/…` indica el build compilado. Laravel usa `public/hot` para seleccionar Vite; sin ese archivo usa `public/build/manifest.json`.

Cuando se sirve el build, editar un archivo Vue no actualiza la página: ejecuta `npm run build` y recarga el navegador. Esta comprobación evita atribuir a los componentes errores visuales de un build anterior. No crees `public/hot` manualmente ni elimines uno que pertenezca a un Vite activo.

## WhatsApp y PrimeVue

Configura `VITE_PRIMEVUE_LICENSE_KEY` con la licencia PrimeVue; Vite la incorpora al frontend durante el arranque o el build. Las credenciales `WA_*` permanecen en Laravel. Reinicia Vite al cambiar la licencia y limpia la caché de configuración Laravel si cambias credenciales previamente cacheadas.

La aplicación puede utilizarse sin configurar WhatsApp: en ese caso no aparece la sección de vinculación en `/admin/settings` ni se consulta su estado. Para mostrarla, completa los valores de envío y webhook indicados en [WhatsApp](../integrations/whatsapp.md#configuración). El idioma usa `es_MX` por defecto; valores vacíos o solo espacios deshabilitan la sección. Si la configuración estaba cacheada, ejecuta `php artisan config:clear` y recarga la página completa. Cambiar estas variables no requiere reiniciar Vite ni recompilar assets.

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

## Adjuntos de transacciones

El procesamiento usa la cola `attachments` de base de datos, incluida en `composer run dev`. Instala Imagick, ImageMagick con JPEG/PNG/WebP y libheif con HEIC/HEIF; la imagen Docker incluye estas dependencias y comprueba su disponibilidad al construirse. El almacenamiento dedicado vive en `storage/app/private/transaction-attachments` y nunca se publica con `storage:link`.

Para comprobar la instalación del host:

```bash
php --ini
php -r 'echo PHP_BINARY.PHP_EOL; echo extension_loaded("imagick") ? "Imagick disponible\n" : "Imagick no disponible\n";'
```

En Homebrew, instala las bibliotecas con `brew install imagemagick libheif`, instala la extensión mediante `pecl install imagick-3.8.0` del PHP correspondiente y habilita `extension=imagick.so` en el directorio de configuración que indique `php --ini`, si todavía no está habilitada. Instalar la extensión sin habilitarla no basta. Reinicia `composer run dev` después de cambiar PHP o sus extensiones y usa **Reintentar** en los comprobantes fallidos que aún conserven la fuente. El diagnóstico de cola es la comprobación completa; detectar la extensión o el nombre de un códec no prueba una conversión real.

Para usar Docker, construye PHP antes de iniciar los consumidores: `docker compose --env-file apps/finance/.env build php` desde la raíz Git. `attachments-worker` procesa un archivo a la vez y `attachments-maintenance` ejecuta únicamente la limpieza de comprobantes cada hora. No sustituyen el scheduler financiero. Para desarrollar en host, ejecuta `php artisan attachments:maintain` cuando necesites limpiar y `php artisan attachments:diagnose --timeout=120` con el worker activo para comprobar cola, almacenamiento y conversión.

Los límites funcionales están en `config/transaction_attachments.php`; `.env.example` muestra sus overrides opcionales. Las barreras PHP/Nginx admiten solicitudes de 8 MiB y archivos de 6 MiB: aumentar el límite de aplicación requiere actualizar también estas barreras. Los controles de ImageMagick y el contenedor son techos de infraestructura, aunque la configuración de aplicación sea mayor. No se realiza análisis antivirus.

Consulta [comprobantes de transacciones](../domain/transaction-attachments.md) para comportamiento, galería, permisos y límites predeterminados.

## Paquete compartido y Docker

`packages/finance-domain` se instala mediante el repositorio Composer local declarado por Finance. La instalación copia el paquete a `vendor`; después de editarlo ejecuta el comando de sincronización documentado en el manifiesto Composer de Finance. No ejecutes `composer update` general para sincronizar cambios locales.

Compose monta la raíz Git en `/workspace` y usa `/workspace/apps/finance` como directorio de trabajo de PHP y consumidores. Nginx sirve `/workspace/apps/finance/public`, se comunica con PHP por la red interna y no publica un puerto local por defecto. MySQL conserva el volumen `debtstb` y el puerto configurado mediante `DB_PORT`; dentro de Docker utiliza `DB_HOST=mysql` y el puerto interno `3306`. `--env-file apps/finance/.env` alimenta la interpolación Compose; `env_file` introduce las variables de Laravel en PHP y workers. Ejecuta desde la raíz:

```bash
docker compose --env-file apps/finance/.env config --quiet
docker compose --env-file apps/finance/.env build php
docker compose --env-file apps/finance/.env run --rm php composer install
```

Conserva el nombre de proyecto Compose existente al cambiar de layout. Los servicios de adjuntos necesitan las dependencias Composer instaladas; construir la imagen no las instala. La SPA continúa integrada y no requiere workspace npm adicional.

## Desarrollo del paquete compartido

`packages/finance-domain` contiene modelos y permisos, sin Actions ni migraciones. Finance lo instala por copia mediante un repositorio Composer `path`. Después de editarlo, ejecuta `composer run domain:sync` desde `apps/finance`; no actualiza las dependencias externas. Desde el paquete, ejecuta `composer install` y `composer test` para sus pruebas independientes. Las exclusiones `.gitattributes` evitan copiar las dependencias de pruebas dentro del vendor de Finance.

El comando de Telegram resuelve su servicio únicamente al ejecutarse; instalar dependencias o listar comandos no requiere un token de Telegram. Para probar envíos reales sí es necesaria la configuración correspondiente.

## Actualizar un checkout local del layout anterior

Un merge o fast-forward mueve solo archivos versionados. `.env`, `vendor`, `node_modules`, `storage`, caches y assets anteriores pueden permanecer en la raíz, aunque el código ya esté en `apps/finance`. Completar esa transición antes de iniciar la aplicación:

1. Detener los procesos locales que usan el checkout y verificar que los destinos no contengan otros datos. No sobrescribir una configuración existente.
2. Respaldar `.env` y datos persistentes fuera del repositorio, en un directorio privado. Trasladar la configuración y el contenido de `storage` a `apps/finance`, conservando `APP_KEY`, archivos, permisos y sesiones. Revisar rutas absolutas locales y enlaces simbólicos.
3. Trasladar dependencias, assets y temporales a sus ubicaciones equivalentes dentro de Finance. Apartar caches antiguas de bootstrap y vistas compiladas; no reutilizar configuración cacheada del layout anterior.
4. Desde `apps/finance`, ejecutar `composer install`, `composer run domain:sync`, `npm ci` y `npm run build`. Comprobar que Artisan arranca y que no quedan carpetas Laravel locales en la raíz.
5. Conservar el respaldo hasta comprobar el entorno. No ejecutar `git clean`, borrar storage ni regenerar `APP_KEY` para resolver los restos del traslado.

Este procedimiento local no migra bases de datos ni despliega servicios. Para una instalación activa con Docker, utilizar el procedimiento de transición de la guía de despliegue, que preserva además la identidad del volumen MySQL.

## Asistente financiero

El stack optativo del chat se prepara con `make chat-config` y `make chat-setup`, y se ejecuta con `make dev-chat`. Finance debe ejecutarse dentro de Docker para alcanzar la API privada. Consultar [preparación, credenciales y datos locales](assistant.md); el launcher no publica FastAPI ni MCP.

La infraestructura Docker se configura en `.env` raíz; cada aplicación utiliza `apps/<app>/.env`. OpenRouter pertenece a `apps/agents/.env`, y los hashes legales a `apps/mcp/.env`. El archivo `.env.chat` anterior requiere la migración descrita en la guía del asistente.
