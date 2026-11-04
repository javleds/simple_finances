# Agente financiero privado

FastAPI recibe identidad de servicio y actor de Finance. El worker ejecuta un grafo LangGraph con herramientas MCP de solo lectura; OpenRouter se conecta mediante `langchain-openrouter` (`ChatOpenRouter`) y se configura con `AGENT_MODEL`, `OPENROUTER_API_KEY` y opcionalmente `AGENT_MODEL_BASE_URL`. No se consulta MySQL desde Python.

## Preparación y ejecución

Python 3.12–3.13 y uv. Desde este directorio:

```sh
uv sync --frozen
uv run alembic upgrade head
uv run python -m finance_agent.setup_checkpoints
uv run uvicorn finance_agent.api:app --host 0.0.0.0 --port 8000
uv run python -m finance_agent.worker
```

Los dos comandos de migración reciben `DATABASE_URL` del propietario del esquema. API, worker y limpieza reciben el rol runtime sin permisos DDL. La creación de checkpoints se ejecuta explícitamente; no ocurre durante el arranque del worker. La instalación ordinaria utiliza el stack Docker documentado en `docs/operations/local-development.md`; no publicar el puerto de FastAPI ni el MCP.

Configurar `apps/agents/.env` desde su `.env.example` o mediante `make chat-config` en la raíz. Docker carga ese mismo archivo mediante `env_file`. No utilizar una clave del proveedor real en pruebas deterministas. `FINANCE_AGENT_SERVICE_TOKEN` y `AGENT_MCP_SERVICE_TOKEN` son credenciales diferentes; ninguna se incorpora a mensajes, checkpoints o URLs. Los logs del worker solo contienen identificador del run y tipo de excepción.

## Contrato

Las rutas `/internal/v1/threads` y sus recursos `messages`, `runs`, `events` y `cancel` requieren `Authorization: Bearer ...`. Los POST incluyen `actor_user_id` entero positivo y `channel`; los GET los reciben como query. El actor siempre procede del gateway autenticado, nunca del navegador ni del modelo. Las respuestas internas son JSON directo; Finance aplica su envelope público `data`.

Los mensajes reciben `client_request_id` UUID, `message` y un contexto `scope: global|account`, `account_id: string|null`, `route_name: string|null`. La misma clave con contenido diferente devuelve409; otra ejecución activa devuelve409. Se persiste un único run activo por thread mediante restricción PostgreSQL y lock transaccional.

El historial devuelve los últimos100 mensajes en orden cronológico; `next_cursor` recupera la página anterior. El estado del run contiene `thread_id`, `run_id`, `status`, `message` y `error`. SSE admite `after`, secuencias persistidas, heartbeats y `message.completed` con el mensaje completo como payload. Cortar SSE no cancela el worker; cancelar establece un estado terminal y evita publicar resultados posteriores.

Cada lectura de historial, polling, replay idempotente y evento final revalida sus referencias contra MCP. El contexto del modelo se reconstruye únicamente con historial autorizado; nunca se expone ni se reanuda ciegamente un checkpoint antiguo. Las respuestas heredan conservadoramente las referencias de todo el historial suministrado, incluso sin nuevas llamadas a herramientas. Un mensaje mixto se oculta entero cuando pierde acceso a una cuenta.

El grafo usa hasta12 llamadas de herramientas,100 registros por página,1000 detalles por run y120 segundos, configurables dentro de límites. La respuesta final es estructurada; los importes se obtienen de herramientas y las referencias se adjuntan desde sus resultados, fuera del control del modelo. Participación y desembolso personal no están implementados: el agente debe aclarar el alcance y ofrecer gasto total de cuentas.

## Persistencia y retención

PostgreSQL almacena threads, mensajes, runs, eventos y checkpoints. El lease se renueva mientras se ejecuta el run. Un lease vencido se marca `worker_interrupted`; no se repite automáticamente una petición de resultado incierto. Para reintentar un fallo terminal, el usuario realiza un nuevo envío explícito.

```sh
uv run python -m finance_agent.cleanup
```

La tarea elimina conversaciones inactivas anteriores a `AGENT_RETENTION_DAYS` (propuesta30), incluidos checkpoints. Bloquea el thread y excluye runs activos antes de borrar, coordinándose con nuevos envíos. Confirmar plazo de retención y tratamiento del proveedor antes del piloto productivo.

## Validación

```sh
uv run pytest
uv run ruff check .
uv run ruff format --check .
uv run mypy src
AGENT_TEST_DATABASE_URL=postgresql+psycopg://runtime:password@localhost:5432/test_agent uv run pytest tests/test_postgres.py
```

La suite PostgreSQL requiere una base efímera previamente migrada y el rol runtime: comprueba exclusión de runs concurrentes, recuperación tras reinicio, checkpoints reales y rechazo de DDL. Usa exclusivamente el actor ficticio90001. No ejecutar contra datos reales.

`evaluations/cases.json` define los casos de aceptación para un modelo real sobre fixtures ficticios. Son evaluaciones separadas: comprobar herramientas, cifras, referencias y aclaraciones, además del texto. Registrar proveedor/modelo, latencia y consumo al ejecutarlas; no afirmar calidad semántica real a partir de los dobles de CI. El SDK MCP también se prueba contra la app Laravel real para verificar propagación aislada de headers entre actores.
