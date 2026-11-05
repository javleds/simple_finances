# Runtime completo del chat en desarrollo

## Contexto
`make dev` inicia Finance y Studio, pero no el FastAPI que atiende `/internal/v1`. Finance en host no resuelve el DNS privado `agent-api`.

## Objetivo
Iniciar el chat completo conservando Studio en 2024 y la privacidad de producción.

## Alcance
Override Compose local, lanzador de agentes, URL local del gateway, pruebas y documentación.

## Fuera de alcance
Migrar automáticamente, crear MySQL, modificar credenciales o publicar servicios de producción.

## Comportamiento esperado
Finance usa localhost:8001 al arrancar con make dev. Agentes inicia PostgreSQL, FastAPI, worker, MCP y Studio. Ctrl+C detiene runtime y Studio; conserva PostgreSQL y sus datos.

## Decisiones técnicas
Reutilizar Compose del chat y el supervisor existente. Override exclusivo con API en 127.0.0.1:8001 y MCP en 127.0.0.1:8002 para Studio. MCP conserva su usuario lector y usa el host/puerto de la base de Finance; loopback se traduce a host.docker.internal. No se inicia el servicio mysql. La conexión PostgreSQL sigue interna.

## Componentes afectados
Scripts dev de Finance/agentes, nuevo lanzador Node y override Compose.

## Backend
Sin cambios a endpoints ni reglas de dominio. Mantener tokens y validación del actor.

## Frontend
Sin cambios visuales.

## Persistencia
Esquema existente; ejecutar make migrate previamente. No aplicar seeds ni migraciones en el arranque.

## API
Gateway host → localhost:8001 → API privada autenticada. Studio → localhost:8002 → MCP autenticado.

## Validaciones
Validar tokens coincidentes y configuración MySQL antes de arrancar. Propagar fallos de procesos. Puertos ocupados hacen fallar el arranque.

## Permisos y seguridad
Publicaciones exclusivamente loopback en un archivo no utilizado en producción. No imprimir secretos. MCP usa sus credenciales de lectura, nunca las de Finance.

## Casos límite
Base heredada de otro proyecto Docker; falta de configuración; fallo de build; apagado durante arranque; ejecución selectiva SERVICES=finance o agents.

## Estrategia de pruebas
Pruebas de configuración local y procesos con dobles, regresión del supervisor y resolución Compose local/producción.

## Estrategia de validación
make dev-test, sintaxis de scripts, docker compose config sin imprimir secretos y comprobación de puertos.

## Estrategia de commits
Un commit funcional con scripts, override, pruebas y documentación por tratarse de una unidad operativa.

## Archivos estimados a modificar
scripts/agents-dev.mjs, scripts/agents-dev.test.mjs, scripts/finance-dev.test.mjs, apps/{agents,finance}/scripts/dev.sh, docker-compose.chat.host.yml, Makefile y guías de desarrollo/asistente.

## Riesgos
MCP requiere usuario lector y hashes legales ya configurados. El primer build puede tardar. No compartir simultáneamente el runtime Docker completo y el runtime host.

## Preguntas abiertas
Ninguna bloqueante; puertos locales fijos 8001 y 8002.

## Plan de implementación
1. Añadir override local reutilizando servicios existentes; comprobar que solo API/MCP publican loopback.
2. Conectar el lanzador al dev script de agentes y seleccionar la URL local en Finance; conservar Studio 2024.
3. Validar configuración, apagado y fallos; actualizar guías y registrar resultados.

## Resultado de validación

Implementado. `make dev-test`: 20 pruebas aprobadas, incluyendo propagación de errores, interrupción, selección de URL, credenciales y conexión al MySQL existente. Compose resuelto con configuración ficticia: API/MCP sin puertos en el stack privado y exclusivamente loopback con el override host. Sintaxis de Bash/Node y diff verificados. No se ejecutaron migraciones ni se validó una conversación real con OpenRouter.
