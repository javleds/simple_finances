# Despliegue seguro del monorepo y asistente

## Contexto
El entrypoint despliega Finance/SPA y adjuntos, pero omite MCP, PostgreSQL, migraciones y consumidores del agente. Es un solo repositorio con varias aplicaciones; no deben actualizarse checkouts distintos.
## Objetivo
Desplegar conjuntamente las aplicaciones habilitadas, manteniendo tráfico cerrado ante fallos y preservando identidad Compose y datos.
## Alcance
Deploy local ejecutado por operador, validación de env separados, composición de producción con chat cuando CHAT_ENABLED=true, builds, migraciones, readiness y pruebas simuladas de comandos.
## Fuera de alcance
Desplegar ahora, SSH, provisión automática de usuarios DB, rotación de secretos, restauración o rollback de datos, uso del modelo real.
## Comportamiento esperado
Preflight antes de mantenimiento; consumidores detenidos antes de actualizar código. Finance siempre; chat solo si está habilitado. No usar overrides locales ni publicar API/MCP. Una instalación chat deshabilitada detiene consumidores anteriores del mismo proyecto. Mantenimiento se retira solo tras migrar y comprobar todos los servicios requeridos.
## Decisiones técnicas
Usar nombre Compose explícito de Finance; raíz .env aporta infraestructura del chat y no sustituye credenciales de Finance. Rechazar proyectos/env incompatibles. Validator Node se ejecuta con el servicio assets (sin requerir Node en host). No imprimir secretos. Si git pull cambia deploy.sh, detener y pedir reejecución: Bash no debe continuar una versión anterior del script sobre código nuevo. Revalidar configuración después de pull.
## Componentes afectados
Root deploy.sh, scripts de validación/pruebas, imágenes/composición chat y documentación operativa.
## Backend
Migraciones MySQL preceden al arranque de MCP nuevo. Migrador owner prepara esquema/checkpoints PostgreSQL antes de API/worker. Diagnóstico sin invocar modelo: DB MCP solo lectura, conectividad MCP y esquema del agente, autenticación Finance→API.
## Frontend
Conservar build/publicación de assets actual; sin cambios UI.
## Persistencia
Conservar volúmenes; no down -v ni rollback automático. Rechazar cambio de identidad de MySQL y proyecto compartido de chat. Copias de seguridad y supervisores externos siguen siendo responsabilidad del operador.
## API
FastAPI/MCP internos, sin puertos del override host. Solo Nginx Finance se publica en Traefik.
## Validaciones
Envs por aplicación, tokens coincidentes, URLs internas, usuario runtime PostgreSQL, lector MySQL separado, hashes legales y modelo configurados, APP_ENV/DEBUG seguros. Redactar diagnósticos.
## Permisos y seguridad
No obtener datos financieros ni usar clave real para checks. MCP no migra MySQL ni obtiene permisos de escritura. Detener API/workers antiguos antes de migraciones para evitar contratos incompatibles.
## Casos límite
Primera instalación, chat apagado, error de build/migración/diagnóstico, proyecto distinto, modificación del propio script durante pull y reintento bajo mantenimiento.
## Estrategia de pruebas
Mocks Docker/Git verifican orden, fail-closed, configuración y ausencia de operaciones destructivas. Pruebas unitarias del validador con secretos ficticios y diagnóstico local sintáctico.
## Estrategia de validación
bash -n, node --test de despliegue/configuración, sintaxis PHP/Python y Compose config sin publicar secretos cuando disponible. No ejecutar despliegue real.
## Estrategia de commits
1. fix(deploy): validate and deploy enabled monorepo services safely
## Archivos estimados a modificar
Deploy.sh, scripts/validate-production.mjs y pruebas, apps/finance/scripts/deploy.test.mjs, scripts de diagnóstico, .dockerignore, docs/operations/deployment.md.
## Riesgos
Hay downtime por mounts de código. Parada de workers puede terminar runs; el mecanismo de leases los marca fallidos. No hay rollback automático de migraciones. Supervisores fuera de Compose deben detenerse externamente.
## Preguntas abiertas
Ninguna bloqueante; no se asume un proyecto nuevo para bases existentes.
## Plan de implementación
1. Crear preflight determinista y pruebas con env ficticios.
2. Integrar composición/orden/parada y fallos del script con tests de comandos.
3. Añadir diagnósticos sin modelo, validar y actualizar operación/recuperación.

## Resultado
Implementado despliegue condicional del chat, preflight sin secretos, conservación de identidad Compose, parada de consumidores/HTTP antes de cambios, migraciones ordenadas y diagnósticos antes de abrir tráfico. Comparación de hashes legales vigentes con Finance evita arrancar un MCP que deniegue a todos los usuarios por configuración antigua. El cambio del entrypoint tras pull requiere reejecución explícita.

Validación local: 41 pruebas del validador de producción, 4 de configuración existente, 17 de secuencia de despliegue, 7 de privilegios MCP y 3 del diagnóstico del agente; 72 aprobadas. Bash/PHP syntax, Ruff/mypy y diff-check aprobados. Compose real renderizado con env ficticios confirmó redes internas, ausencia de puertos publicados y dependencia saludable de MCP. Revisión independiente aplicada. No se construyeron/desplegaron servicios de producción, no hubo SSH ni llamadas al modelo o uso de credenciales reales. Los diagnósticos operativos se ejecutarán sobre los servicios reales cuando el operador despliegue.
