# LangGraph Studio en desarrollo local

## Contexto
`make dev` descubre scripts de apps; el grafo financiero se compila dentro del worker.
## Objetivo
Iniciar LangGraph dev en 127.0.0.1:2024 y visualizar el grafo real desde Studio.
## Alcance
Script agents, CLI dev, langgraph.json y builder reutilizable.
## Fuera de alcance
Publicar FastAPI/MCP, modificar Docker productivo o sustituir persistencia del worker.
## Comportamiento esperado
Inicio junto a Finance, selección con SERVICES y cierre supervisado. Visualización sin claves ni MCP. Ejecución manual con actor fijado en entorno de desarrollo.
## Decisiones técnicas
Extraer build_graph manteniendo nodos/aristas y checkpointer productivo. Studio usa dependencias diferidas y persistencia local del CLI. Sin túnel ni escucha pública.
## Componentes afectados
apps/agents, descubrimiento del launcher y docs locales.
## Backend
Mismo grafo, límites y reglas; adaptador local para dependencias diferidas.
## Frontend
Sin cambios.
## Persistencia
Estado CLI local ignorado por Git, separado de conversaciones Finance.
## API
Servidor dev estándar en 2024; FastAPI permanece privada.
## Validaciones
Actor positivo y credenciales requeridos para ejecutar consultas.
## Permisos y seguridad
Actor fuera del input del modelo; autorización MCP vigente. Tracing optativo, loopback obligatorio.
## Casos límite
Falta uv/credenciales, puerto ocupado y DNS Docker inaccesible desde host.
## Estrategia de pruebas
Regresión Python, compilación sin credenciales, supervisor Node y endpoint real del grafo.
## Estrategia de validación
Pytest, Ruff, mypy, Node tests, bash -n y smoke del servidor local.
## Estrategia de commits
Un commit feat(dev) con pruebas y docs.
## Archivos estimados a modificar
apps/agents/{langgraph.json,pyproject.toml,uv.lock,scripts/dev.sh,src/finance_agent/graph.py,src/finance_agent/studio.py}, ejemplos y docs.
## Riesgos
El host no resuelve MCP Docker; ejecución requiere un MCP alcanzable. Visualización no requiere conexión.
## Preguntas abiertas
Ninguna para visualización.
## Plan de implementación
1. Extraer builder y adaptador diferido, validar regresión.
2. Añadir CLI y script, comprobar descubrimiento y servidor real.
3. Documentar URLs y alcance, registrar validación y commit.

## Resultado
Implementado con CLI solo en dependencias dev y builder compartido. Validado `make dev SERVICES=agents`: `/ok` responde correctamente y `/assistants/finance/graph` devuelve los nodos y aristas reales. 34 pruebas Python aprobadas (3 PostgreSQL omitidas), 7 pruebas del supervisor Node aprobadas, Ruff y mypy correctos. No se realizaron llamadas a un modelo ni consultas financieras reales.
