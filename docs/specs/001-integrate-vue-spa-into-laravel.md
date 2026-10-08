# Integración de Vue en Laravel

## Contexto y objetivo
Laravel y Vue viven en dos repositorios y despliegues. Consolidar ambos en simple_finances, conservando sus historiales, módulos Vue y contratos HTTP. Laravel sirve el HTML y los assets compilados; composer run dev inicia PHP y Vite.

## Alcance y comportamiento esperado
Vue se traslada a resources/js; configuraciones y dependencias Node a la raíz Laravel. fin-si.com sirve aplicación y /api; api.finsi.com conserva API, webhooks y enlaces firmados existentes. Las rutas profundas cargan Vue. API, healthcheck y archivos estáticos no caen en el catch-all.

## Fuera de alcance
Cambios de dominio financiero, esquema de datos, autenticación, permisos, Inertia, SSR, rediseño visual y ejecución del despliegue remoto.

## Decisiones técnicas
Importar Git mediante subtree sin squash ni reescritura. Un único package.json y Vite 8 con laravel-vite-plugin 3, Vue y Tailwind 4. Router con base / independiente de /build. Cliente HTTP con /api por defecto y override opcional. Node 24. Desarrollo en host; producción conserva Compose y volumen de código.

## Componentes afectados
Backend: entrada Blade, rutas web, post-deploy y scripts Composer. Frontend: traslado de módulos existentes, configuración, API client y scripts Playwright. Infraestructura: Compose, Nginx y despliegue. Reutilizar todos los componentes compartidos existentes, sin crear UI.

## Persistencia, API, validaciones y seguridad
Sin migraciones ni cambios JSON. Conservar JWT Bearer, permisos y ledger financiero. Catch-all solo GET/HEAD fuera de prefijos reservados. Mantener APP_KEY y host original de enlaces firmados. No publicar secretos ni artefactos locales.

## Casos límite
Puertos ocupados, finalización de procesos, assets inexistentes, 401, rutas profundas, build fallido, pestañas con assets anteriores y rollback. public/hot no debe existir en producción.

## Estrategia de pruebas y validación
Línea base PHP/Vitest; composer validate, type-check, Vitest, build, PHPUnit, format:check, route:cache y Compose config. Pruebas de entrada SPA, exclusión de API/assets, base HTTP y redirección 401. Smoke con HMR y build sin Vite; validar enlaces firmados y reembolsos compartidos.

## Plan de implementación
1. Registrar commits de origen y pruebas base.
2. Importar historial SPA bajo frontend-import y trasladar código/configuración.
3. Unificar herramientas, dependencias y alias.
4. Servir entrada Blade y conservar URLs de navegación/API.
5. Coordinar PHP/Vite mediante Composer y concurrently.
6. Construir assets con servicio Node bajo demanda; publicar manifest después de assets, conservar anteriores.
7. Unificar reglas Traefik y despliegue de un checkout.
8. Validar integración y actualizar documentación.

## Estrategia de commits
Especificación; importación historial; traslado; integración HTTP/dev; despliegue; pruebas/documentación. Mensajes convencionales en inglés.

## Archivos estimados
resources/js, resources/views/spa.blade.php, routes/web.php, composer.json, package.json, configuraciones frontend, scripts, docker-compose.prod.yml, docker/nginx/default.conf, deploy.sh y docs.

## Riesgos y operación
El montaje de código oculta assets incluidos solo en imágenes: construir en el checkout montado. Conservar volumen MySQL, nombres de servicios y despliegue anterior durante transición. Rollback restaura commit, manifest/assets y reglas Traefik. Archivar SPA únicamente después de validar producción.

## Preguntas abiertas
Ninguna decisión de producto pendiente. Despliegue remoto y archivado no se ejecutan como parte del cambio local.
