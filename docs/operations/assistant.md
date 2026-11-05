# Asistente financiero local

El chat público pasa por Laravel Finance. FastAPI, el worker LangGraph, MCP y PostgreSQL se ejecutan dentro de Docker; no tienen puertos publicados ni rutas Traefik. El MCP consulta MySQL con un usuario que solo tiene SELECT. El modelo y su configuración pertenecen a Python, no a la SPA.

## Preparación

Requisitos: Docker Compose 2.24.4 o superior (override `!reset`), Node 24, dependencias de Finance instaladas y build Vue actualizado. Las imágenes se construyen desde la raíz del monorepo. No se realizan migraciones ni importaciones sobre una base externa automáticamente.

```bash
make chat-config
```

Prepara archivos con permisos 0600 y credenciales aleatorias. Conserva los valores existentes y rechaza tokens incompatibles antes de escribir. Cada aplicación carga exclusivamente su archivo; Docker no reemplaza su configuración con valores de otra aplicación.

| Archivo | Configuración |
| --- | --- |
| `.env` | Nombre del proyecto Docker, puerto web, base MySQL y credencial root, credenciales de aprovisionamiento PostgreSQL |
| `apps/finance/.env` | Laravel, conexión MySQL, `CHAT_ENABLED`, `CHAT_AGENT_URL`, `FINANCE_AGENT_SERVICE_TOKEN` |
| `apps/mcp/.env` | Laravel MCP, MySQL de lectura, `AGENT_MCP_SERVICE_TOKEN`, hashes legales |
| `apps/agents/.env` | OpenRouter, modelo, conexión PostgreSQL runtime, MCP y tokens internos |

Configura `AGENT_MODEL` y `OPENROUTER_API_KEY` en `apps/agents/.env`; no usar variables `VITE_*`. El inicializador completa `DATABASE_URL` y ambos pares de tokens cuando faltan. Si cambias una credencial compartida, actualiza sus dos consumidores. La raíz conserva las contraseñas PostgreSQL para aprovisionar roles; cambiarla no rota una base existente ni reemplaza el `DATABASE_URL` de una aplicación ya configurada.

Ejecuta `php artisan chat:legal-hashes` desde `apps/finance` y copia los hashes a `apps/mcp/.env`. Repetir cuando cambien documentos legales. MCP rechaza acceso si los hashes faltan o la aceptación no coincide.

Para Finance existente, conserva su configuración y adapta explícitamente su conexión al MySQL de Docker (`DB_HOST=mysql`, puerto 3306, base/credencial correspondiente). Habilita `CHAT_ENABLED=true` y configura `APP_URL`, `SPA_URL` y `FRONTEND_URL` con `http://127.0.0.1:8080` o el puerto elegido. El inicializador no cambia la conexión existente. Finance nuevo requiere además su preparación Laravel habitual, incluyendo APP_KEY y JWT. MCP usa `DB_USERNAME=finance_mcp` y su propia contraseña de lectura.

`COMPOSE_PROJECT_NAME=fin-si-chat` separa volúmenes locales. MySQL empieza vacío si no existe su volumen. No se importan datos ni se modifican passwords existentes automáticamente. El `.env` raíz determina su aprovisionamiento; los archivos de las aplicaciones determinan cómo se conectan.

```bash
make chat-setup
```

Construye imágenes, inicia MySQL y PostgreSQL, crea/actualiza el usuario `finance_mcp` limitado a SELECT y aplica migraciones del agente. La contraseña del usuario PostgreSQL de runtime se crea solo al inicializar un volumen nuevo; cambiar la variable no rota un rol existente. La rotación debe ejecutar ALTER ROLE de forma administrativa sin eliminar el volumen.

Para crear el esquema Finance en este MySQL local nuevo, ejecutar explícitamente:

```bash
docker compose --env-file .env -f docker-compose.yml -f docker-compose.chat.yml -f docker-compose.chat.local.yml --profile chat run --rm php php artisan migrate
```

No crear usuarios financieros ficticios con credenciales conocidas en entornos compartidos. Usar el registro habitual o fixtures únicamente en un entorno de pruebas. El usuario del chat necesita email verificado y aceptación legal vigente.

Compilar la SPA y detener cualquier Vite de host que escriba `public/hot` en este checkout:

```bash
cd apps/finance
npm run build
cd ../..
make dev-chat
```

El launcher exige que no exista `apps/finance/public/hot`, para que Laravel use el build. Si quedó un archivo después de detener Vite, comprobar que es obsoleto antes de retirarlo. No se elimina automáticamente. Abrir `http://127.0.0.1:8080`; `CHAT_WEB_PORT` permite cambiarlo. El botón de chat aparece en el navbar autenticado. Con `CHAT_ENABLED=false`, abre un estado no disponible sin llamar al agente; habilitarlo y cumplir los requisitos de sesión permite conversar.

`make dev` mantiene el desarrollo habitual en host; no puede resolver el DNS privado de FastAPI. Para probar chat completo usar Finance dentro del stack Docker. No publicar FastAPI para adaptar ese modo host.

```bash
make chat-down
```

Detiene el proyecto sin eliminar volúmenes. No utilizar `down -v` con conversaciones o datos que se quieran conservar.

## Redes y procesos

- `php` → `agent-api` por `chat_gateway`: credencial FINANCE_AGENT_SERVICE_TOKEN e identidad derivada del guard de Finance.
- API/worker → PostgreSQL por `agent_state`: usuario runtime sin DDL; migrador separado como propietario.
- API/worker → MCP por `agent_tools`: API lo usa para revalidar acceso al historial; worker para herramientas.
- MCP web → FPM por `mcp_web`, FPM → MySQL por `mcp_finance_data`.
- Solo worker usa `agent_egress` para HTTPS al proveedor. No dispone de credenciales MySQL.

Nginx Finance no bufferiza `/api/chat/` y mantiene lecturas de 150 segundos. SSE transporta estados y respuesta; desconectarse no cancela el run durable. El usuario puede cancelar desde el panel. Todos los endpoints internos funcionales exigen credencial; los de salud no entregan datos privados.

## Datos y límites

El grafo tiene memoria por thread en PostgreSQL. Los balances se consultan de nuevo; no se reutilizan como verdad financiera desde el historial. La cuenta mostrada en la etiqueta del chat es contexto del próximo mensaje, no autorización. Una petición explícita puede consultar otra cuenta visible o todas.

El agente solo lee finanzas, pero sí escribe conversaciones y checkpoints. El proveedor puede recibir descripciones/importes necesarios para el análisis. No activar en producción sin definir proveedor/modelo, presupuesto y retención. No registrar tokens ni mensajes completos como logs por defecto.

Los gastos se presentan como total de cuentas cuando ese es el alcance: no equipararlos a participación personal ni a desembolso del usuario. La clasificación inferida no escribe categorías. Los resultados parciales deben identificarse.

## Producción y rollback

La composición de producción parte de `docker-compose.prod.yml` + `docker-compose.chat.yml` (sin el override local). No hay despliegue remoto automático. Aprovisionar credenciales, migraciones y hashes legales antes de habilitar `CHAT_ENABLED`. Verificar que Traefik solo enrute Finance y no añada routers para servicios del chat.

Los archivos `.env` son una opción local; usar el mecanismo de secretos del entorno de despliegue. Los ficheros env de ejemplo nunca contienen valores reales. El servicio `agent-maintenance` ejecuta limpieza al arrancar y cada 24 horas; `AGENT_RETENTION_DAYS` define el plazo (30 por defecto). Solo elimina conversaciones elegibles sin runs activos, junto con eventos y checkpoints. Mantener backup/restore de PostgreSQL y MySQL por separado, con pruebas de recuperación. El rol propietario de PostgreSQL se usa solo para migraciones/operación; nunca por el worker/API.

Rollback funcional: deshabilitar CHAT_ENABLED y parar worker/API/MCP sin tocar tablas de Finance ni borrar volúmenes. Mantener compatibilidad de esquemas al volver a una imagen previa. La implementación y sus limitaciones se registran en [la especificación](../specs/015-agente-financiero-chat-mcp.md).

## Proveedor del modelo

Las llamadas usan el conector oficial de LangChain `ChatOpenRouter`, dentro del grafo existente. `AGENT_MODEL_BASE_URL` apunta por defecto a `https://openrouter.ai/api/v1`. El modelo elegido debe soportar llamadas a herramientas y salida estructurada JSON Schema. Configura estos valores en `apps/agents/.env`. No es necesario cambiar FastAPI, MCP ni los clientes del chat.

## Migración desde `.env.chat`

Antes de ejecutar `make chat-config`, distribuye los valores existentes sin generar contraseñas nuevas para volúmenes existentes:

- Raíz `.env`: `COMPOSE_PROJECT_NAME`, `CHAT_WEB_PORT`, `DB_DATABASE`, `DB_PASSWORD`, `AGENT_DATABASE_OWNER_PASSWORD`, `AGENT_DATABASE_PASSWORD`.
- Finance: `CHAT_ENABLED` y `FINANCE_AGENT_SERVICE_TOKEN`; conserva/configura la conexión MySQL en su propio archivo.
- MCP: `MCP_APP_KEY` pasa a `APP_KEY`, `MCP_DATABASE_PASSWORD` pasa a `DB_PASSWORD`, y conserva los hashes legales y `AGENT_MCP_SERVICE_TOKEN`. Usa `DB_USERNAME=finance_mcp` y la base correcta.
- Agentes: claves `OPENROUTER_*`, `AGENT_MODEL`, `AGENT_MODEL_BASE_URL`, `AGENT_RETENTION_DAYS` y ambos tokens. `DATABASE_URL` utiliza el rol `agent_runtime` y su contraseña PostgreSQL anterior; codificar caracteres especiales en la URL. `MCP_URL=http://agent-mcp:8080/mcp`.

Retira `.env.chat` del checkout después de verificar la distribución (cualquier copia de respaldo debe permanecer privada). `chat-config` se detiene si detecta ese archivo para evitar crear credenciales distintas accidentalmente. Reinicia los contenedores para aplicar los nuevos env; no elimines volúmenes.

## Conversaciones en el navbar

El panel no cambia la ruta de Finance. En desktop ocupa una ventana inferior derecha; en móvil, toda la pantalla. Historial sustituye el transcript dentro de esa ventana. Nuevo chat crea un UUID en FastAPI; volver a una conversación conserva su ID y el contexto del siguiente mensaje se obtiene de la pantalla actual. Cerrar o cambiar de conversación no cancela un run; Detener sí lo solicita. Los borradores se conservan en memoria durante la sesión.

El título se infiere en la respuesta estructurada del primer intercambio válido y se guarda una sola vez. Si no hay título válido, la UI muestra una fecha; al revocar acceso a referencias se oculta también el título. No se envían peticiones adicionales al modelo solo para titular.

Para actualizar un stack existente, construir la imagen del agente y aplicar la migración aditiva `002_conversation_titles` antes de reiniciar API/worker:

```bash
make migrate-chat
```

El comando construye la imagen e inicia PostgreSQL, esperando a que esté disponible. El migrador usa la credencial propietaria; la API sigue usando el rol runtime. Compilar Finance con `npm run build` y reiniciar el stack con `make dev-chat`. En producción usar su archivo base sin el override local. No hace falta migrar MySQL. El esquema acepta threads previos sin título; el rollback funcional mantiene las columnas aditivas.
