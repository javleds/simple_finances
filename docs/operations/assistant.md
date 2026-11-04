# Asistente financiero local

El chat público pasa por Laravel Finance. FastAPI, el worker LangGraph, MCP y PostgreSQL se ejecutan dentro de Docker; no tienen puertos publicados ni rutas Traefik. El MCP consulta MySQL con un usuario que solo tiene SELECT. El modelo y su configuración pertenecen a Python, no a la SPA.

## Preparación

Requisitos: Docker Compose 2.24.4 o superior (override `!reset`), Node 24, dependencias de Finance instaladas y build Vue actualizado. Las imágenes se construyen desde la raíz del monorepo. No se realizan migraciones ni importaciones sobre una base externa automáticamente.

```bash
make chat-config
```

Crea `.env.chat` con permisos 0600 y contraseñas/tokens aleatorios. No muestra secretos ni reemplaza una configuración existente. Configura el modelo y la clave del proveedor en ese archivo; no pegarlos en el chat ni usar variables `VITE_*`. Configura los hashes legales mediante el comando de Finance `php artisan chat:legal-hashes` desde `apps/finance`; copiar sus valores a `.env.chat`. Repetir cuando cambien documentos legales. MCP rechaza acceso si los hashes faltan o la aceptación no coincide.

El `COMPOSE_PROJECT_NAME=fin-si-chat` separa los volúmenes locales de este stack de otros proyectos Docker. Su MySQL comienza vacío si no existe ese volumen. Para utilizar un conjunto de datos local existente, importarlo o elegir deliberadamente una configuración compatible; no apuntar pruebas a producción. `.env.chat` determina DB_DATABASE y la contraseña root de este MySQL. Finance conserva el resto de su configuración local de `apps/finance/.env`.

```bash
make chat-setup
```

Construye imágenes, inicia MySQL y PostgreSQL, crea/actualiza el usuario `finance_mcp` limitado a SELECT y aplica migraciones del agente. La contraseña del usuario PostgreSQL de runtime se crea solo al inicializar un volumen nuevo; cambiar la variable no rota un rol existente. La rotación debe ejecutar ALTER ROLE de forma administrativa sin eliminar el volumen.

Para crear el esquema Finance en este MySQL local nuevo, ejecutar explícitamente:

```bash
docker compose --env-file .env.chat -f docker-compose.yml -f docker-compose.chat.yml -f docker-compose.chat.local.yml --profile chat run --rm php php artisan migrate
```

No crear usuarios financieros ficticios con credenciales conocidas en entornos compartidos. Usar el registro habitual o fixtures únicamente en un entorno de pruebas. El usuario del chat necesita email verificado y aceptación legal vigente.

Compilar la SPA y detener cualquier Vite de host que escriba `public/hot` en este checkout:

```bash
cd apps/finance
npm run build
cd ../..
make dev-chat
```

El launcher exige que no exista `apps/finance/public/hot`, para que Laravel use el build. Si quedó un archivo después de detener Vite, comprobar que es obsoleto antes de retirarlo. No se elimina automáticamente. Abrir `http://127.0.0.1:8080`; `CHAT_WEB_PORT` permite cambiarlo. El chat aparece solo cuando `CHAT_ENABLED=true` y la sesión tiene acceso.

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

Los secretos de `.env.chat` son una opción local; usar el mecanismo de secretos del entorno de despliegue. Los ficheros env de ejemplo nunca contienen valores reales. El servicio `agent-maintenance` ejecuta limpieza al arrancar y cada 24 horas; `AGENT_RETENTION_DAYS` define el plazo (30 por defecto). Solo elimina conversaciones elegibles sin runs activos, junto con eventos y checkpoints. Mantener backup/restore de PostgreSQL y MySQL por separado, con pruebas de recuperación. El rol propietario de PostgreSQL se usa solo para migraciones/operación; nunca por el worker/API.

Rollback funcional: deshabilitar CHAT_ENABLED y parar worker/API/MCP sin tocar tablas de Finance ni borrar volúmenes. Mantener compatibilidad de esquemas al volver a una imagen previa. La implementación y sus limitaciones se registran en [la especificación](../specs/015-agente-financiero-chat-mcp.md).
