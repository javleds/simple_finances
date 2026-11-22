# Parada observable del despliegue

## Contexto y objetivo
El despliegue usa --time obsoleto, muestra comandos en múltiples líneas y espera 130 segundos para cada servicio. El worker Python no maneja SIGTERM. Hacer visible la espera y permitir parada ordenada.

## Alcance y comportamiento
Usar --timeout, registrar servicio y presupuesto, seleccionar solo contenedores activos y respetar plazos por servicio: API 30, worker 130, mantenimiento y MCP 10 segundos. Mantener el orden API, worker, mantenimiento, MCP para conservar las dependencias del trabajo activo. El worker deja de reclamar trabajo al recibir TERM/INT, termina el actual y sale; Docker conserva el límite final.

## Fuera de alcance
No conectar al servidor ni ejecutar despliegues, borrar contenedores o volúmenes, cambiar rutas, redes o credenciales.

## Componentes y decisiones técnicas
Reutilizar deploy.sh, su suite Node con Docker simulado y el bucle worker asyncio. Corregir unión de argumentos en logs. Silenciar huérfanos solo en el preflight deliberadamente parcial mediante COMPOSE_IGNORE_ORPHANS, sin eliminar recursos. La comprobación completa conserva los avisos.

## Backend, frontend, persistencia y API
Solo shell y ciclo de vida del worker. Sin cambios de API, UI, esquemas ni permisos financieros.

## Validaciones y seguridad
No cambiar código hasta detener consumidores. Preservar mantenimiento ante fallos, selección por etiquetas del proyecto y aislamiento de PostgreSQL. Probar plazos, orden, exclusión de contenedores detenidos y drenaje del worker.

## Casos límite y riesgos
Un trabajo activo puede tardar hasta el plazo del contenedor; llamadas síncronas pueden retrasar la señal. Contenedores de la versión anterior solo incorporan el manejador tras reconstruirse. El daemon puede tardar más que el plazo en responder. No prometer un máximo global de despliegue.

## Estrategia de pruebas y validación
Suite de deploy con Docker simulado, bash -n, pruebas Python del worker, Ruff y mypy. No requiere servidor.

## Estrategia de commits
fix(deploy): improve graceful shutdown and deployment diagnostics

## Archivos estimados
 deploy.sh, apps/finance/scripts/deploy.test.mjs, apps/agents/src/finance_agent/worker.py, apps/agents/tests/test_worker.py, docs/operations/deployment.md.

## Preguntas abiertas
Sin necesidad de datos adicionales para esta corrección; no se conoce qué proceso corresponde al ID del log remoto.

## Plan de implementación
1. Actualizar parada y logs del script; ampliar pruebas existentes.
2. Añadir señal de drenaje al worker y probar salida inactiva y trabajo en curso.
3. Documentar espera y primera actualización; validar y crear commit local.
