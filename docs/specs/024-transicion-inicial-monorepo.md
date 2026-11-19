# Transición inicial desde Laravel al monorepo

## Contexto
Producción usa ~/apps/traefik y ~/apps/simple_finances; este último conserva Laravel en raíz. El script de transición existente exige cuatro argumentos y no respalda MySQL ni ofrece preflight aislado.
## Objetivo
Migrar una vez el checkout existente al layout apps/finance y packages, conservando identidad Docker, datos y configuración. Traefik permanece en su directorio y no se administra desde la transición.
## Alcance
Ampliar docker/transition-monorepo.sh con opciones nombradas, --check predeterminado, --apply explícito, ruta habitual por defecto, autodetección Compose y backup de MySQL/storage/env antes del checkout. Conservar invocación histórica de cuatro argumentos.
## Fuera de alcance
Ejecutar sobre producción, SSH, cambiar dominios o secretos, migrar otro checkout SPA, activar el agente, restaurar automáticamente bases o administrar supervisores externos.
## Comportamiento esperado
El script se copia fuera del checkout antes de ejecutarse. --check valida sin parar servicios ni crear respaldos. --apply verifica commit local, layout, Git limpio, proyecto/volumen, ruta de backup nueva, configuración mínima y red proxy; activa mantenimiento, detiene procesos Compose y crea backup antes de mover runtime y cambiar revisión. Despliega Finance con --no-pull; chat deshabilitado explícitamente para esta transición.
## Decisiones técnicas
Mantener misma carpeta simple_finances: cambia su contenido, no su padre ni traefik. Resolver físicamente ruta padre del backup para rechazar enlaces al checkout. Target debe existir localmente y contener archivos monorepo/deploy/env de ejemplo. Detectar proyecto desde contenedor MySQL y comparar volumen. Bash no interpreta el .env como código. Bloqueo compartido con deploy. Guardar etapa y revisión en respaldo; cualquier fallo posterior a mantenimiento conserva servicios detenidos.
## Componentes afectados
Script de transición, pruebas del despliegue y guía operativa.
## Backend
Dump MySQL con mysqldump del contenedor, usando variables internas sin mostrar contraseña. No regenerar APP_KEY. Restaurar archivos persistentes y caches necesarias con permisos originales. Inicializar env nuevos con placeholders, sin activar chat.
## Frontend
Retener assets/manifest previos y dejar que deploy reconstruya/publica los nuevos. No administrar Traefik ni modificar sus archivos.
## Persistencia
Backup privado incluye SQL comprimido, env, storage, assets, revisión y Compose. No borrar volúmenes ni ejecutar down -v. Paquetes/dependencias antiguos se retiran a backup antes del checkout. Rollback manual con información de etapa.
## API
Sin cambios de contratos. Dominios/URLs se conservan; exigir configuración mínima antes de detener tráfico.
## Validaciones
Target y archivos requeridos, root Laravel real, checkout limpio, backup fuera del repositorio, mismo proyecto/volumen y ausencia de env destino preexistentes. No sobrescribir cambios ignorados de futuras apps.
## Permisos y seguridad
No imprimir secretos. Backups modo privado. Detener schedulers/consumidores externos previamente es responsabilidad del operador. No conexiones remotas.
## Casos límite
Ya migrado, destino inválido, volumen inesperado, backup con symlink, dump fallido, checkout fallido, falta de configuración, primer deploy fallido y reejecución accidental.
## Estrategia de pruebas
Fixture con Git real y Docker simulado: --check sin mutaciones, aplicación completa, fallo de dump sin checkout, fallos posteriores sin reapertura, env/APP_KEY/storage preservados, Traefik intacto y rechazo de rutas inseguras. Mantener pruebas históricas de la transición.
## Estrategia de validación
bash -n, node --test de transición/despliegue, diff-check. No ejecutar el script sobre el directorio real.
## Estrategia de commits
fix(deploy): support checked first-time monorepo transition
## Archivos estimados a modificar
Docker/transition-monorepo.sh, scripts/transition-monorepo.test.mjs, apps/finance/scripts/deploy.test.mjs, docs/operations/deployment.md.
## Riesgos
Downtime por checkout con mounts. El dump requiere MySQL vigente y espacio suficiente; writers externos deben estar detenidos. Nuevas migraciones pueden impedir rollback de código sin evaluación.
## Preguntas abiertas
Ninguna bloqueante; preflight muestra claves faltantes y no inventa dominios.
## Plan de implementación
1. Añadir CLI/preflight y validación de archivos/identidad.
2. Ordenar backup/dump/checkout/restauración/configuración y recuperación.
3. Probar con fixtures y documentar comandos para ~/apps/simple_finances.

## Resultado
Implementada CLI con preflight predeterminado y aplicación explícita, ruta habitual y proyecto autodetectado. Se conserva la invocación histórica. Backup MySQL comprimido antes del checkout, protección de enlaces de configuración/rutas, verificación de base/host, env por aplicación, conservación de APP_KEY y transición inicial Finance-only. Traefik y volúmenes permanecen intactos; recuperación documentada por etapa.

Validación: 13 pruebas de transición con Git real/Docker simulado y 17 pruebas de despliegue aprobadas (30 total), bash -n y diff-check. Se verificaron datos/secretos originales, permisos privados, rutas con symlink, dump vacío/fallido, fallo de checkout, fallo de deploy y target incorrecto. No se ejecutó migración real, SSH ni operación sobre servicios del usuario.
