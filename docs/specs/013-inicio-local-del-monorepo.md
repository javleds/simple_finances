# Inicio local del monorepo

## Contexto

`composer run dev` funciona desde `apps/finance` y utiliza su script Bash para iniciar PHP, Vite y colas con concurrently. La raíz carece de un comando que coordine aplicaciones independientes.

## Objetivo

Ofrecer `make dev`, selección mediante `SERVICES` y listado de servicios sin dependencias adicionales.

## Alcance

Makefile raíz, supervisor Node 24 tipado, pruebas de procesos y documentación. Cada aplicación ofrece `apps/<nombre>/scripts/dev.sh`.

## Fuera de alcance

Crear MCP/agentes, iniciar bases de datos, instalar dependencias automáticamente, ejecutar migraciones o modificar producción.

## Comportamiento esperado

`make dev` inicia todas las aplicaciones que tienen entrypoint; actualmente solo Finance. `make dev SERVICES="finance"` selecciona una o varias. `make dev-list` lista las disponibles. Validar todos los nombres antes de arrancar. Ctrl+C detiene grupos de procesos; una salida de servicio detiene los demás y conserva su código de error.

## Decisiones técnicas

Node 24 ya requerido por Finance; TypeScript nativo en archivo `.mts`, sin librerías nuevas. Bash conserva responsabilidad de cada servicio. Supervisión de grupos de procesos para macOS/Linux, con SIGTERM y límite de cinco segundos antes de SIGKILL. Nombres de servicio restringidos, sin interpolación de comandos del usuario.

## Componentes afectados

Makefile raíz, scripts de supervisión/pruebas y documentación vigente. Se reutiliza `apps/finance/scripts/dev.sh` sin duplicar sus comandos.

## Backend

Sin cambios de dominio. El script existente sigue arrancando PHP y worker.

## Frontend

Sin cambios de UI; Vite continúa en el entrypoint de Finance.

## Persistencia

Sin cambios ni conexiones automáticas a nuevas bases de datos.

## API

Sin cambios de contratos ni puertos existentes.

## Validaciones

Nombres válidos, servicios existentes, duplicados eliminados y todos los entrypoints comprobados antes del primer spawn.

## Permisos y seguridad

Ejecutar solo scripts del repositorio seleccionados explícitamente o descubiertos por convención; no ejecutar cadenas shell recibidas por argumentos. No imprimir configuración ni secretos.

## Casos límite

Servicio inexistente, ningún servicio disponible, salida temprana, Ctrl+C, fallo de lanzamiento y proceso hijo que ignora SIGTERM.

## Estrategia de pruebas

Pruebas Node con aplicaciones temporales: descubrimiento/listado, selección, rechazo antes de iniciar, propagación de errores y terminación de procesos hijos. No iniciar la aplicación real ni sus consumidores durante esas pruebas.

## Estrategia de validación

`make dev-list`, pruebas del supervisor y chequeo TypeScript usando el compilador existente de Finance. Mantener la documentación de comandos alineada.

## Estrategia de commits

Un commit convencional `feat: add root development service launcher` incluye implementación, pruebas y documentación, al ser una capacidad pequeña y cohesiva.

## Archivos estimados a modificar

`Makefile`, `.nvmrc`, `scripts/dev.mts`, `scripts/dev.test.mjs`, `README.md`, `AGENTS.md` y `docs/operations/local-development.md`.

## Riesgos

Supervisión diseñada para macOS/Linux, consistente con scripts Bash existentes. `make dev` incluirá automáticamente aplicaciones futuras que implementen el entrypoint; usar selección explícita si no se desean todas.

## Preguntas abiertas

Ninguna para este alcance; MCP/agentes se integrarán cuando existan y definan sus comandos reales.

## Plan de implementación

1. Crear supervisor con descubrimiento y apagado conjunto; verificar con procesos temporales.
2. Exponer targets Make y Node 24 desde la raíz; comprobar listado y selección inválida.
3. Actualizar documentación, ejecutar chequeo de tipos y registrar resultados junto al commit.

## Resultado

Implementado: `make dev`, `make dev-list` y `make dev-test`. Siete pruebas de supervisión pasan, incluyendo Ctrl+C, selección por variable SERVICES y terminación forzada. El chequeo TypeScript estricto pasa. No se iniciaron consumidores de la aplicación real durante las pruebas ni se crearon MCP/agentes.
