# Despliegue integrado

## Versión PHP

La imagen compartida por producción y desarrollo fija PHP 8.4.27 mediante `php:8.4.27-fpm-trixie`, incorporando la [actualización de seguridad de PHP 8.4.27](https://www.php.net/releases/8_4_27.php). Las futuras actualizaciones de PHP requieren cambiar explícitamente esta etiqueta. Mantener `composer.lock` sin regenerarlo en producción. El despliegue reconstruye PHP con `--pull` y comprueba `composer check-platform-reqs --no-dev --lock` dentro de esa imagen.

Después de desplegar, verificar la versión activa con `docker compose --env-file apps/finance/.env -f docker-compose.prod.yml exec -T php php -v`; debe indicar PHP 8.4.27.

## Contrato operativo

Un checkout de `simple_finances` contiene Laravel y Vue en `apps/finance` y el paquete Composer en `packages/finance-domain`. Ejecuta `deploy.sh` desde la raíz Git; los comandos de aplicación se ejecutan desde `apps/finance`. `bash deploy.sh` actualiza ese repositorio; no despliega otro checkout ni reinicia Traefik. El wrapper del workspace anterior delega a `api/deploy.sh`.

Conserva el nombre del proyecto Docker Compose utilizado en producción: cambiar el directorio o `COMPOSE_PROJECT_NAME` puede seleccionar otro volumen de MySQL aunque el nombre lógico siga siendo `simplefinancesdb`. Verifica el volumen existente antes de iniciar un nuevo checkout. Se conservan nombres de contenedores y redes.

PHP y el constructor Node 24 ejecutan como UID/GID 1001. El checkout, `vendor`, `node_modules`, `storage`, `bootstrap/cache` y `public/build` deben ser escribibles por ese usuario. Prepara permisos durante aprovisionamiento; el script no cambia propietarios.

Configura `apps/finance/.env` con el `COMPOSE_PROJECT_NAME` real de la instalación existente (valor sin comillas) y:

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

El script valida configuración y Git limpio, registra el commit anterior, activa mantenimiento y detiene los consumidores de adjuntos/chat y HTTP antes de `git pull --ff-only`, construye PHP, instala Composer sin dependencias de desarrollo y ejecuta el constructor Node bajo demanda. Si el pull cambia el propio `deploy.sh`, aborta con los servicios detenidos: reejecutar `bash deploy.sh --no-pull` utiliza el entrypoint nuevo. El código está montado: actualizar Git antes del mantenimiento expondría versiones mezcladas.

`npm ci` y el build compilan a `storage/app/deploy-assets/build`. El publicador valida la entrada Vue y archivos del manifest, copia assets, conserva los anteriores, guarda `manifest.previous.json` y cambia el manifest mediante rename. Retira `public/hot`.

Después inicia PHP/MySQL/Nginx. MySQL dispone de un healthcheck TCP con `mysqladmin ping`; PHP espera a que MySQL esté saludable y `compose up --wait` espera esta comprobación antes de ejecutar `app:post-deploy --migrate`. El healthcheck comprueba disponibilidad del servidor; las credenciales se validan al conectar Laravel. Después de migrar y cachear, recrea los consumidores de adjuntos y ejecuta `attachments:diagnose --timeout=120`, que comprueba procesamiento real mediante la cola. El script desactiva mantenimiento solo si todo termina correctamente. Un fallo posterior al inicio del despliegue detiene consumidores de adjuntos y del chat, preservando los datos y jobs pendientes. Un fallo de preflight no detiene servicios. Un error posterior al mantenimiento deja la aplicación cerrada; consulta el log, corrige el fallo y repite el script. No limpies assets retenidos hasta cerrar la ventana de rollback.

## Primera transición al monorepo

Para una instalación con `~/apps/traefik` y `~/apps/simple_finances`, ejecutar `docker/transition-monorepo.sh` desde una copia fuera del checkout. La carpeta `simple_finances` conserva su nombre; su contenido cambia a `apps/finance`, `apps/mcp`, `apps/agents` y `packages`. No se mueve, edita ni reinicia `~/apps/traefik`; se reutiliza la red externa `proxy`.

El script antiguo de deploy no puede hacer esta transición por su propio pull. Primero publicar/obtener la revisión monorepo que incluye ambos scripts y descargarla sin cambiar los archivos activos:

```bash
cd ~/apps/simple_finances
git fetch origin
target_revision="$(git rev-parse origin/main)"
git show "$target_revision:docker/transition-monorepo.sh" > /tmp/fin-si-transition.sh
backup_dir="$HOME/apps/simple_finances-backup-$(date +%Y%m%d-%H%M%S)"
bash /tmp/fin-si-transition.sh --target "$target_revision" --backup "$backup_dir" --check
```

`--check` es el modo predeterminado. Verifica layout Laravel original, Git limpio, commit objetivo disponible, archivos de destino, configuración mínima, proyecto/volumen MySQL y red proxy. No detiene servicios, modifica archivos ni crea un respaldo. Detecta el proyecto desde las etiquetas de `simple_finances_db_prod`; `--project` permite exigir un valor conocido. `--repo` cambia el checkout; por defecto usa `~/apps/simple_finances`. El padre de `--backup` debe existir, y la carpeta debe ser nueva y estar físicamente fuera del checkout. Se rechazan enlaces simbólicos de `.env`, storage y apps para evitar sobrescribir destinos externos.

El `.env` antiguo debe conservar `APP_KEY`, `DB_DATABASE`, `DB_PASSWORD` y tener `APP_ENV=production`, `APP_DEBUG=false`, `TRAEFIK_APP_NAME`, `APP_DOMAIN`, `API_DOMAIN`, `APP_URL`, `SPA_URL` y `FRONTEND_URL`. El preflight identifica claves faltantes sin mostrar secretos ni inventar dominios. El procedimiento está dirigido al MySQL del Compose existente, con volumen `${COMPOSE_PROJECT_NAME}_simplefinancesdb`; otra base, bind mount o volumen personalizado requiere adaptar el procedimiento antes de continuar. El preflight exige `DB_CONNECTION=mysql`, `DB_HOST=mysql`, puerto interno 3306 y que `DB_DATABASE` coincida con `MYSQL_DATABASE` del contenedor, usando valores literales simples. La credencial root del contenedor debe permitir respaldarla.

Revisar espacio suficiente para el dump y dos copias de storage/assets. Detener previamente schedulers, consumidores y otros escritores administrados fuera de este Compose, incluidos contenedores huérfanos; el script no puede descubrir todos los supervisores externos. Ensayar con una copia aislada antes de producción. Después de revisar el resultado del preflight:

```bash
bash /tmp/fin-si-transition.sh --target "$target_revision" --backup "$backup_dir" --apply
```

La aplicación se pone en mantenimiento y se detienen los servicios Compose existentes salvo MySQL. El respaldo privado incluye revisión anterior/objetivo, identidad Compose/volumen, `.env`, Compose, `mysql.sql.gz`, storage, assets/manifest y dependencias/caches antiguas. El dump usa el cliente del contenedor con `--single-transaction`, rutinas, eventos y triggers; no imprime contraseña. Una falla o dump vacío impide el checkout. Los escritores externos deben seguir detenidos para mantener coherencia con los archivos.

Después cambia al commit objetivo en detached HEAD, copia `.env` a `apps/finance/.env`, conserva `APP_KEY` y dominios, fija el proyecto detectado y `CHAT_ENABLED=false`. La primera transición despliega solo Finance. El nuevo `.env` raíz conserva identidad/valores de infraestructura MySQL y deja credenciales PostgreSQL pendientes; los env de MCP y agente se crean desde ejemplos, sin generar secretos. Todos los env tienen permisos 600 y el respaldo 700. Los archivos persistentes se restauran en `apps/finance/storage` y se ejecuta el nuevo `deploy.sh --no-pull` para reconstruir dependencias/assets, migrar y verificar antes de abrir tráfico.

Configurar después los env de MCP/agente para producción, aprovisionar sus credenciales y permisos, completar infraestructura y hashes legales antes de habilitar `CHAT_ENABLED=true`. No habilitar chat con los valores de ejemplo. Actualizar rutas de supervisores externos a `/workspace/apps/finance` o `apps/finance` en host y reiniciarlos solo tras verificar la aplicación. Para despliegues posteriores seleccionar la rama de entrega habitual; `--no-pull` permite mantener una revisión fijada.

El archivo `stage` del respaldo registra hasta dónde avanzó. Si indica `preserve-runtime` o `checkout`, algunos archivos pueden estar únicamente en el respaldo: restaurarlos antes de reabrir el layout anterior. Si indica `restore-layout`, completar y verificar la copia de storage/assets y env antes de intentar deploy; no abrir tráfico con una restauración parcial. Si falla después del checkout, no reejecutar la transición: conservar mantenimiento, corregir la nueva configuración y ejecutar `bash deploy.sh --no-pull`, o seguir el rollback. Si falla durante el dump, los archivos Laravel originales siguen en su sitio; corregir la causa y elegir un directorio de respaldo nuevo. No ejecutar `down -v` ni regenerar `APP_KEY`. La invocación histórica de cuatro argumentos (`CHECKOUT COMMIT BACKUP PROJECT`) se conserva y aplica inmediatamente.

### Rollback del traslado

Mantén mantenimiento y detén Nginx, PHP, scheduler y todos los consumidores antes de revertir. Conserva primero una copia actual de `apps/finance/storage` y `.env` fuera del checkout; no restaures un respaldo antiguo sobre adjuntos creados después de habilitar tráfico. Registra la revisión fallida y preserva logs.

Restaura el commit registrado en `previous-commit`, la configuración Compose anterior y el mismo nombre de proyecto/volumen. Copia el storage actual a `storage` en raíz y el `.env` a raíz, instala las dependencias de esa revisión y reconstruye sus assets o restaura el manifest y assets que correspondan exactamente a ella. Regenera caches y `storage:link` desde el layout anterior. Recrea contenedores con los mounts antiguos, restaura rutas de supervisores y verifica salud, autenticación y permisos antes de `artisan up` y de reanudar consumidores. Una reversión Git sola no recupera mounts, storage, dependencias ni assets.

El traslado no agrega migraciones. `app:post-deploy --migrate` sí puede ejecutar migraciones previamente pendientes: revisa su compatibilidad antes de revertir; nunca reviertas datos automáticamente. Conserva aliases de clases y el mapa morph mientras existan notificaciones o jobs con nombres anteriores.

## Operación de WhatsApp

Configura las variables de [WhatsApp](../integrations/whatsapp.md), aplica sus migraciones y mantén `QUEUE_CONNECTION=database`. La conexión de `jobs` debe coincidir con la de `webhook_receipts`, con `retry_after` mayor que el timeout de 60 segundos del job; el valor predeterminado es 90 segundos.

La sección de vinculación de `/admin/settings` solo aparece cuando los seis valores `services.whatsapp` de envío y webhook están completos; `WA_AUTH_TEMPLATE_LANGUAGE` conserva el valor predeterminado `es_MX`. Si falta alguno o está vacío, la tarjeta se oculta y no consulta la conexión. Laravel publica únicamente un booleano en el HTML; las credenciales permanecen en el servidor y las conexiones guardadas se conservan. Tras cambiar las variables, regenera la caché con `php artisan config:cache` y recarga la página completa. Este cambio de configuración no requiere otro build del frontend. Consulta [las variables requeridas](../integrations/whatsapp.md#configuración).

El despliegue actual no aprovisiona un worker supervisado. Para habilitar procesamiento continuo, configura un proceso persistente con `php artisan queue:work database --sleep=1 --tries=5 --timeout=60`, reinícialo al cambiar código mediante el mecanismo de supervisión elegido y verifica que consuma la cola `default`. El worker incluido en `composer run dev` es para desarrollo.

Registra en Meta la URL HTTPS pública `/api/whatsapp/webhook`, verifica el GET y suscribe el campo `messages`. Comprueba firma, persistencia y procesamiento del POST por separado: un `200` confirma recepción durable, no procesamiento de negocio. Revisa `php artisan queue:failed` y reintenta jobs corregidos con `php artisan queue:retry <uuid>`.

## Recuperación

Para fallos de build o post-deploy, mantén mantenimiento, corrige el problema y repite `bash deploy.sh`. El script registra el commit anterior en el log.

Para volver a una versión integrada anterior, restaura primero ese commit mediante el procedimiento operativo habitual, instala sus dependencias y restaura el manifest correspondiente desde `public/build/manifest.previous.json` si corresponde a esa versión; de otro modo reconstruye sus assets. Regenera las caches de Laravel y desactiva mantenimiento tras verificar. No basta cambiar el manifest dejando PHP actualizado.

Para volver a una versión anterior a la integración, restaura el backend y su configuración Compose/Traefik anterior, reinicia el contenedor SPA conservado y devuelve el dominio principal a su router. Esa versión no contiene Vue embebido: restaurar solo un manifest no recupera el frontend. La integración de la SPA no añadió migraciones; WhatsApp sí incorpora tablas nuevas. Además, `post-deploy --migrate` puede ejecutar otras pendientes; evalúa su compatibilidad antes de revertir código y no reviertas datos automáticamente.

## Operación de adjuntos

El comportamiento de subida, galería, permisos, límites predeterminados y contrato API se documenta en [comprobantes de transacciones](../domain/transaction-attachments.md).

`attachments-worker` y `attachments-maintenance` usan la misma imagen PHP, UID/GID 1001 y montaje `/workspace` y directorio de trabajo `/workspace/apps/finance` de la aplicación. Ambos pertenecen solo a la red interna; no publican puertos ni rutas Traefik. Nginx conserva la única entrada HTTP pública y MySQL permanece interno. El worker consume exclusivamente `attachments`, una instancia inicial con 1 GiB de memoria y parada gradual de 90 segundos. `--force` permite comprobar la cola durante mantenimiento; el despliegue lo detiene antes de modificar código. El consumidor existente de `default` sigue siendo responsabilidad de su supervisor actual.

El servicio de mantenimiento ejecuta `attachments:maintain --interval=3600`, no `schedule:work`: evita duplicar tareas financieras. Retira fuentes fallidas y temporales huérfanos tras 24 horas, completa borrados y reconcilia capacidad. Para ejecutarlo manualmente usa `docker compose --env-file apps/finance/.env -f docker-compose.prod.yml exec -T php php artisan attachments:maintain`. Los servicios reinician automáticamente; consulta `docker compose --env-file apps/finance/.env -f docker-compose.prod.yml logs --tail=100 attachments-worker attachments-maintenance` y `php artisan queue:failed`. Reintenta mediante la interfaz del adjunto cuando conserve su fuente; un archivo expirado exige nueva selección. Investiga el error antes de usar `queue:retry`.

Configura límites mediante `TRANSACTION_ATTACHMENTS_*` en `apps/finance/.env`; regenera la caché y recrea los consumidores al cambiarlos. Los valores iniciales reservan 1 GiB libre y limitan el catálogo a 5 GiB, incluyendo temporales y reservas de procesamiento. La política ImageMagick restringe formatos, delegados y acceso indirecto, con techos de 256 MiB de memoria/mapa/disco, un hilo y 45 segundos. La aplicación puede imponer límites menores; elevar estos techos requiere reconstruir la imagen. PHP admite 6 MiB por archivo y 8 MiB por solicitud; Nginx admite 8 MiB. Para aumentar límites coordina esos techos y `retry_after` con el timeout del job. No existe antivirus en este MVP.

Respaldar conjuntamente la base de datos y `storage/app/private/transaction-attachments` durante una ventana consistente, con consumidores detenidos. La base conserva estados, permisos y reservas; los archivos solos no permiten recuperar el catálogo. Restaurar ambos antes de reiniciar consumidores y ejecutar el diagnóstico. No incluir comprobantes en Git ni respaldos públicos.

ImageMagick, Imagick y libheif tienen licencias abiertas; los paquetes del sistema incluyen sus avisos. La disponibilidad de un códec no constituye una garantía universal sobre patentes. Las imágenes se convierten a WebP y se descarta el original: conservar un respaldo del comprobante original fuera de la aplicación si es necesario.


## Despliegue conjunto de Finance, MCP y agente

Es un monorepo: las aplicaciones y el paquete compartido se despliegan desde la misma revisión Git. `bash deploy.sh` siempre despliega Finance/SPA. Si `CHAT_ENABLED=true` en `apps/finance/.env`, incorpora `docker-compose.chat.yml` al archivo de producción; nunca utiliza los overrides local/host ni publica puertos del agente, MCP o PostgreSQL. Solo Nginx Finance conserva los routers Traefik.

El preflight ejecuta Node 24 en el contenedor `assets`, sin requerir Node en el host. Exige `APP_ENV=production` y `APP_DEBUG=false` en Finance y MCP. Con chat habilitado valida los cuatro archivos separados: `.env`, `apps/finance/.env`, `apps/mcp/.env` y `apps/agents/.env`. La raíz aporta credenciales PostgreSQL; Finance conserva su configuración propia. Los nombres de proyecto deben coincidir con el de producción existente. No copiar `COMPOSE_PROJECT_NAME=fin-si-chat` de desarrollo sobre una instalación existente ni cambiar el nombre para resolver conflictos: eso puede seleccionar otros volúmenes. Registrar y respaldar tanto MySQL como PostgreSQL antes de adoptar este flujo; un stack de chat previo en otro proyecto requiere reconciliación explícita, no migración automática de volúmenes.

Los tokens entre aplicaciones deben coincidir por pareja y ser distintos entre fronteras. FastAPI usa `agent_runtime` y la contraseña de infraestructura correspondiente; el migrador usa `agent_owner`. La contraseña owner debe contener caracteres seguros para URL (`A-Z`, `a-z`, dígitos, `.`, `_`, `~`, `-`), pues Compose la inserta en su DSN. El lector MCP debe estar aprovisionado con solo SELECT, separado del escritor de Finance. Se requieren modelo/clave configurados, pero el despliegue no consulta al modelo. Usar valores literales en los env, con comillas simples para secretos que contienen `$`; no depender de expansiones `${VAR}` entre archivos. El script evita overrides del shell para las variables que interpolan infraestructura/routers.

Con chat activo, la secuencia adicional es:

1. Detener API, worker, mantenimiento y MCP anteriores antes de cambiar código. No detener ni borrar volúmenes.
2. Construir imágenes de agente y MCP desde la revisión actual; MCP instala una copia nueva del paquete compartido. Las imágenes excluyen caches PHP locales para no incorporar configuración/secretos de desarrollo.
3. Migrar y cachear Finance; iniciar PostgreSQL y ejecutar `agent-migrate` para Alembic y checkpoints con el rol owner.
4. Recrear MCP y API, esperar salud de MCP/HTTP y comprobar SELECT-only en MySQL, coincidencia de hashes legales con `GetLegalDocuments` de Finance, esquema del agente y conectividad interna. Un HEAD a un UUID inexistente comprueba autenticación Finance→API sin crear conversaciones ni consultar datos financieros.
5. Recrear worker/mantenimiento del agente, diagnosticar adjuntos y retirar mantenimiento al finalizar todas las comprobaciones.

Si el chat está deshabilitado, el script no exige sus env ni lo inicia; sí detiene consumidores del chat que existan en ese mismo proyecto. PostgreSQL y sus datos permanecen. Schedulers o workers supervisados fuera de Compose siguen requiriendo parada y reinicio manual; el script no puede administrarlos.

Para la primera adopción desde un `deploy.sh` antiguo, no confiar en que ese script cambie de comportamiento tras su propio pull. Durante una ventana de mantenimiento, detener HTTP y consumidores con la configuración vigente, actualizar la revisión por el procedimiento operativo y ejecutar el nuevo `bash deploy.sh --no-pull`. Mantener respaldos y la identidad de los volúmenes. Esta tarea no realiza ese procedimiento en el servidor.

Los checks no sustituyen un E2E con usuario verificado y aceptación legal vigente, ni prueban disponibilidad de OpenRouter. Si se modifica un documento legal, actualizar explícitamente los hashes MCP antes de reintentar. Ante un fallo, mantener mantenimiento y consumidores detenidos, corregir y reejecutar; no hacer rollback automático de migraciones ni usar `down -v`. Al revertir código, revisar compatibilidad de MySQL y PostgreSQL y reconstruir todas las aplicaciones de la misma revisión.


### Espera al detener el chat

La parada muestra servicio, contenedor, plazo y duración al terminar. El orden conserva MCP disponible mientras termina el worker: API (30 s), worker (130 s), mantenimiento (10 s), Nginx MCP (10 s) y PHP MCP (10 s), solo para contenedores activos. El plazo corresponde a la parada gradual de cada contenedor, no al despliegue completo. El worker recibe TERM/INT, deja de reclamar trabajo y termina el actual; si excede el plazo, Docker fuerza su terminación. Los contenedores antiguos todavía pueden agotar los 130 s hasta que se reconstruyan con este manejador. No interrumpir únicamente por ver dos minutos de espera en ese paso.

El preflight usa deliberadamente solo el Compose base y omite allí la detección de huérfanos; los contenedores del chat no están sobrando ni deben eliminarse con `--remove-orphans`. La validación con la configuración completa conserva sus avisos. `--timeout` sustituye al flag obsoleto `--time`. No se alteran PostgreSQL ni sus volúmenes durante la parada.
