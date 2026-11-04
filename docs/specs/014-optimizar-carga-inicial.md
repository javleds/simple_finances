# Optimizar la carga inicial de Finance

## Contexto

El login solicita componentes ajenos a autenticación mediante el índice compartido. El CSS de gráficos también se importa desde `main.ts`. El CSS base y el tema ya se aplican antes de Vue. La medición anterior de desarrollo registró 123 recursos; el build local del worktree requiere 916733 bytes de JavaScript (229478 gzip estimados) para main, layout y login con sus dependencias estáticas.

## Objetivo

Reducir dependencias iniciales sin modificar comportamiento ni aspecto de autenticación o gráficos.

## Alcance

Imports directos en todas las páginas y layouts de autenticación; estilos de gráficos junto a DashboardBalanceChart; comparación del build y navegador.

## Fuera de alcance

Loader, SSR, cambios de router, actualización de librerías, despliegue e integración en main.

## Comportamiento esperado

Login, registro, recuperación, verificación y documentos legales mantienen controles y validaciones. Los gráficos conservan estilos al navegar al dashboard. Autenticación no descarga calendarios, filtros ni selectores que no utiliza.

## Decisiones técnicas

Reutilizar AppButton, AppCard, AppInput, AppLink, AppPasswordInput, AppText, AppTitle y AppThemeMenu mediante imports a sus archivos Vue. Mantener el índice para consumidores existentes. Mover ambos imports chart-style al componente que importa vue-chart. Evitar agrupaciones manuales de chunks hasta medir el resultado.

## Componentes afectados

Páginas y layout de auth, main.ts, DashboardBalanceChart.vue y documentación de frontend.

## Backend

Sin modificaciones.

## Frontend

Cambiar únicamente resolución de imports y ubicación del CSS de gráficos. Auditar dependencias transitivas y tamaño real del build para detectar agrupación residual.

## Persistencia

Sin cambios.

## API

Sin cambios de contratos ni llamadas.

## Validaciones

Preservar formularios y mensajes existentes.

## Permisos y seguridad

No modificar guardas de rutas, tokens ni permisos. Navegador aislado sin credenciales para mediciones.

## Casos límite

Entrada directa en cada ruta de auth, tema oscuro y navegación posterior hacia gráficos. Los imports dinámicos deben conservar la asociación del CSS al gráfico.

## Estrategia de pruebas

Ejecutar pruebas existentes de auth, tema y formularios. No añadir pruebas que solo repliquen imports; comprobar recursos del build y del navegador.

## Estrategia de validación

npm run build incluye type-check. Ejecutar Vitest dirigido, git diff --check y medir cierre de dependencias de main/layout/login antes y después. Comparar gzip estimado, no confundir con transferencia real del servidor. Iniciar Vite del worktree en puerto alternativo y servir HTML de prueba aislado; no cambiar el servidor del usuario.

## Estrategia de commits

1. docs: plan initial load optimization
2. perf: reduce authentication startup dependencies (código, documentación y resultados).

## Archivos estimados a modificar

resources/js/modules/auth/{layouts,pages}/*.vue, resources/js/main.ts, resources/js/modules/admin/components/DashboardBalanceChart.vue, docs/frontend/primevue.md y esta especificación.

## Riesgos

El bundler podría seguir agrupando dependencias usadas por imports al índice en otras rutas. La mejora de desarrollo no garantiza el mismo porcentaje de reducción en producción. El servidor Vite caliente no representa su primer arranque.

## Preguntas abiertas

Ninguna bloqueante; medir antes de ampliar alcance.

## Plan de implementación

1. Registrar baseline y especificación; revisar grafo del login y consumidor de gráficos.
2. Reemplazar imports de auth y mover CSS; verificar que no queden imports al índice en auth.
3. Compilar y comparar dependencias. Si persiste un chunk que reúne controles ajenos, identificar su causa antes de ampliar cambios.
4. Ejecutar pruebas dirigidas y comprobar en navegador los recursos solicitados. Actualizar resultados y crear commit.
