# Filtros de categorías y comprobantes

## Contexto
Los listados de movimientos permiten búsqueda y filtros de tipo o periodo, pero no categoría ni presencia de comprobantes.

## Objetivo
Encontrar movimientos por una o varias categorías y por presencia o ausencia de adjuntos.

## Alcance
Listado de una cuenta y listado general, escritorio y móvil; opciones, borradores, etiquetas activas, URL, paginación y resumen global.

## Fuera de alcance
Cambiar categorías, permisos, archivos o reglas financieras. No hay migraciones.

## Comportamiento esperado
Categorías múltiples se combinan con OR; «Sin categoría» incluye categoría nula y puede combinarse con categorías. Los otros filtros se combinan con AND. Comprobantes ofrece todos, con y sin; cuenta cualquier adjunto no eliminado, incluidos pendientes y fallidos, igual que el contador. Aplicar confirma; cerrar descarta; limpiar reinicia borradores y conserva la semántica del periodo obligatorio global.

## Decisiones técnicas
Reutilizar AppFilterPanel, AppFilterOptions, AppActiveFilters y adaptadores existentes. Obtener opciones de categorías utilizadas en movimientos visibles para cada listado, sin restringir por periodo, para incluir categorías compartidas y evitar consultas por cuenta. Desambiguar nombres mostrando origen personal/cuenta. Mantener búsqueda y filtros actuales.

## Componentes afectados
Controladores/listado Laravel, validación, consultas, repositorio/tipos Vue, composables de filtros, paneles y páginas.

## Backend
Compartir aplicación explícita de category_ids, uncategorized y has_attachments entre listados antes de paginar/calcular resumen. Opciones consultan categorías utilizadas en el mismo alcance de visibilidad del listado.

## Frontend
Cargar opciones por contexto. Selección múltiple con opción sin categoría. Conservar valores aplicados en URL; enviar has_attachments como 1/0, omitir todos. Resetear scroll/paginación mediante queries existentes. Etiquetas removibles por categoría y adjuntos.

## Persistencia
Sin cambios de esquema ni datos. Usar relaciones existentes y whereHas/whereDoesntHave sobre adjuntos no eliminados.

## API
GET /api/transactions y /api/accounts/{account}/transactions aceptan category_ids (array de enteros positivos o CSV), uncategorized (0/1) y has_attachments (0/1, omitido sin filtro). category_ids y uncategorized usan OR agrupado.
GET /api/transactions/filter-options y /api/accounts/{account}/transactions/filter-options devuelven {data:{categories:[{id,name,account_name}]}}. account_name es nulo para categorías personales. Registrar rutas antes de bindings de transacción.

## Validaciones
Rechazar IDs malformados y booleanos inválidos con 422. Filtrar categorías ajenas produce vacío sin revelar datos. Limitar categorías seleccionadas a 100.

## Permisos y seguridad
Conservar autorización y alcance propios de cada listado. Opciones no exponen categorías ni nombres de cuentas fuera del alcance. No habilitar acceso a archivos por filtrar.

## Casos límite
Sin opciones, nombres duplicados, categoría eliminada/null, adjuntos pendientes/fallidos/eliminados, filtro con false, categorías + sin categoría, búsqueda y paginación, URL restaurada.

## Estrategia de pruebas
API: combinación de categorías/null y presencia/ausencia de adjuntos, permisos de opciones y resumen filtrado. Vue: serialización de false, URL/borradores, aplicar/cerrar/limpiar y etiquetas.

## Estrategia de validación
Pruebas Laravel y Vue, TypeScript, ESLint dirigido, build y diff check.

## Estrategia de commits
Backend y contrato; frontend y pruebas; documentación según coherencia.

## Archivos estimados a modificar
Requests/controladores/servicios Transaction, routes/api.php, pruebas API; módulos transactions/accounts y repositorios de frontend; docs/api.json.

## Riesgos
No perder false en serialización; no filtrar solo la página cargada; no usar catálogo personal para omitir categorías compartidas; no mezclar OR con permisos.

## Preguntas abiertas
Ninguna; el alcance sigue el listado actual.

## Plan de implementación
1. Validar filtros y aplicarlos en consultas autorizadas, antes de paginación/resumen; probar combinaciones y seguridad.
2. Exponer opciones de categorías por contexto y probar alcance sin consultas por cada cuenta.
3. Extender tipos/repositorio/composables y paneles existentes con borradores, URL y etiquetas; probar contratos e interacción.
4. Actualizar contrato API, ejecutar verificaciones y guardar commits locales.
