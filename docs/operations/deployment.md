# Despliegue integrado

## Versión PHP

La imagen usa `php:8.4-fpm` para satisfacer las dependencias Symfony 8 del lockfile probado. Mantener `composer.lock` sin regenerarlo en producción. Reconstruir PHP al desplegar y comprobar `composer check-platform-reqs --no-dev` dentro de esa imagen.

## Contrato operativo

Un checkout de `simple_finances` contiene Laravel y Vue. `bash deploy.sh` actualiza ese repositorio; no despliega otro checkout ni reinicia Traefik. El wrapper del workspace anterior delega a `api/deploy.sh`.

Conserva el nombre del proyecto Docker Compose utilizado en producción: cambiar el directorio o `COMPOSE_PROJECT_NAME` puede seleccionar otro volumen de MySQL aunque el nombre lógico siga siendo `simplefinancesdb`. Verifica el volumen existente antes de iniciar un nuevo checkout. Se conservan nombres de contenedores y redes.

PHP y el constructor Node 24 ejecutan como UID/GID 1001. El checkout, `vendor`, `node_modules`, `storage`, `bootstrap/cache` y `public/build` deben ser escribibles por ese usuario. Prepara permisos durante aprovisionamiento; el script no cambia propietarios.

Configura `.env` con:

```dotenv
APP_DOMAIN=fin-si.com
API_DOMAIN=api.finsi.com
SPA_LEGACY_DOMAIN=v2.fin-si.com
TRAEFIK_APP_NAME=simple-finances-api
APP_URL=https://fin-si.com
SPA_URL=https://fin-si.com
FRONTEND_URL=https://fin-si.com
```

Conserva el identificador Traefik existente si es diferente, `APP_KEY`, secretos y configuración DB. La API antigua sigue respondiendo bajo su host sin redirección; `v2.fin-si.com` redirige al principal. Comprueba enlaces firmados emitidos antes del cambio y webhooks.

## Secuencia

El script valida configuración y Git limpio, registra el commit anterior, activa mantenimiento antes de `git pull --ff-only`, construye PHP, instala Composer sin dependencias de desarrollo y ejecuta el constructor Node bajo demanda. El código está montado: actualizar Git antes del mantenimiento expondría versiones mezcladas.

`npm ci` y el build compilan a `storage/app/deploy-assets/build`. El publicador valida la entrada Vue y archivos del manifest, copia assets, conserva los anteriores, guarda `manifest.previous.json` y cambia el manifest mediante rename. Retira `public/hot`.

Después inicia PHP/MySQL/Nginx. MySQL dispone de un healthcheck TCP con `mysqladmin ping`; PHP espera a que MySQL esté saludable y `compose up --wait` espera esta comprobación antes de ejecutar `app:post-deploy --migrate`. El healthcheck comprueba disponibilidad del servidor; las credenciales se validan al conectar Laravel. El script desactiva mantenimiento solo si todo termina correctamente. Un error posterior al mantenimiento deja la aplicación cerrada; consulta el log, corrige el fallo y repite el script. No limpies assets retenidos hasta cerrar la ventana de rollback.

## Primera transición

1. Guarda el commit backend anterior, configuración Compose/Traefik, imagen/contenedor SPA y respaldo de base de datos. No cambies nombre del proyecto Compose ni volumen.
2. Prepara `.env`, permisos y red externa `proxy`. En una instalación nueva verifica credenciales y volumen MySQL; el healthcheck y la dependencia de PHP impiden ejecutar el post-deploy antes de que el servidor acepte conexiones.
3. Verifica el build integrado y rutas en un entorno de ensayo. El checkout frontend original queda intacto; archívalo únicamente después de verificar producción.
4. En la ventana de mantenimiento, retira el router/contenedor SPA antiguo antes de habilitar las nuevas etiquetas para `fin-si.com`; mantener ambos routers activos genera conflicto. Conserva su imagen y configuración para rollback.
5. Ejecuta el despliegue integrado y verifica `/up`, login, rutas profundas, API antigua, assets y reembolsos. Mantén workers y scheduler existentes según su operación actual.

El script no retira automáticamente el contenedor SPA de otro proyecto ni aprovisiona Traefik. No se ha ejecutado ningún despliegue remoto como parte de esta integración.

## Operación de WhatsApp

Configura las variables de [WhatsApp](../integrations/whatsapp.md), aplica sus migraciones y mantén `QUEUE_CONNECTION=database`. La conexión de `jobs` debe coincidir con la de `webhook_receipts`, con `retry_after` mayor que el timeout de 60 segundos del job; el valor predeterminado es 90 segundos.

La sección de vinculación de `/admin/settings` solo aparece cuando los seis valores `services.whatsapp` de envío y webhook están completos; `WA_AUTH_TEMPLATE_LANGUAGE` conserva el valor predeterminado `es_MX`. Si falta alguno o está vacío, la tarjeta se oculta y no consulta la conexión. Laravel publica únicamente un booleano en el HTML; las credenciales permanecen en el servidor y las conexiones guardadas se conservan. Tras cambiar las variables, regenera la caché con `php artisan config:cache` y recarga la página completa. Este cambio de configuración no requiere otro build del frontend. Consulta [las variables requeridas](../integrations/whatsapp.md#configuración).

El despliegue actual no aprovisiona un worker supervisado. Para habilitar procesamiento continuo, configura un proceso persistente con `php artisan queue:work database --sleep=1 --tries=5 --timeout=60`, reinícialo al cambiar código mediante el mecanismo de supervisión elegido y verifica que consuma la cola `default`. El worker incluido en `composer run dev` es para desarrollo.

Registra en Meta la URL HTTPS pública `/api/whatsapp/webhook`, verifica el GET y suscribe el campo `messages`. Comprueba firma, persistencia y procesamiento del POST por separado: un `200` confirma recepción durable, no procesamiento de negocio. Revisa `php artisan queue:failed` y reintenta jobs corregidos con `php artisan queue:retry <uuid>`.

## Recuperación

Para fallos de build o post-deploy, mantén mantenimiento, corrige el problema y repite `bash deploy.sh`. El script registra el commit anterior en el log.

Para volver a una versión integrada anterior, restaura primero ese commit mediante el procedimiento operativo habitual, instala sus dependencias y restaura el manifest correspondiente desde `public/build/manifest.previous.json` si corresponde a esa versión; de otro modo reconstruye sus assets. Regenera las caches de Laravel y desactiva mantenimiento tras verificar. No basta cambiar el manifest dejando PHP actualizado.

Para volver a una versión anterior a la integración, restaura el backend y su configuración Compose/Traefik anterior, reinicia el contenedor SPA conservado y devuelve el dominio principal a su router. Esa versión no contiene Vue embebido: restaurar solo un manifest no recupera el frontend. La integración de la SPA no añadió migraciones; WhatsApp sí incorpora tablas nuevas. Además, `post-deploy --migrate` puede ejecutar otras pendientes; evalúa su compatibilidad antes de revertir código y no reviertas datos automáticamente.
