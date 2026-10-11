# Selector múltiple de categorías

## Contexto y objetivo
El filtro de categorías ocupa demasiado espacio con casillas. Sustituirlo por un selector múltiple con búsqueda en ambos listados.

## Alcance y comportamiento esperado
Conservar selección múltiple, «Sin categoría», borradores, límite de 100 categorías, etiquetas y URL. Mostrar hasta dos nombres y resumir selecciones adicionales con un contador; permitir limpiar la selección. Mantener 24 px entre grupos de filtros y 8 px entre etiqueta y control, con etiquetas de altura consistente.

## Fuera de alcance
Cambios de backend, contratos, persistencia, permisos o filtros de comprobantes.

## Decisiones técnicas y componentes afectados
Crear AppMultiSearchSelect sobre PrimeVue MultiSelect siguiendo AppSearchSelect, registrar exportación e índice en resources/js/AGENTS.md. Reutilizarlo desde TransactionAdditionalFilters, compartido entre ambos paneles. API consultada mediante MCP PrimeVue.

## Backend, persistencia y API
Sin cambios.

## Frontend y validaciones
Normalizar el borrado de PrimeVue a un arreglo vacío. Conservar IDs como cadenas y validación de selección existente. Etiqueta asociada al control y búsqueda integrada.

## Permisos y seguridad
Conservar opciones y alcance existentes.

## Casos límite y riesgos
Selección vacía, varias categorías de igual nombre con origen, etiquetas largas y limpieza que emite null.

## Estrategia de pruebas y validación
Prueba del selector real para seleccionar, buscar y limpiar. Pruebas existentes de filtros, TypeScript, ESLint, validación MCP y build.

## Estrategia de commits
Un commit convencional de interfaz y comprobaciones.

## Archivos estimados a modificar
AppMultiSearchSelect.vue y prueba, exportaciones, AGENTS.md de frontend y TransactionAdditionalFilters.vue.

## Preguntas abiertas
Ninguna.

## Plan de implementación
1. Crear adaptador mínimo según metadata de PrimeVue y registrar componente compartido.
2. Sustituir casillas por selector en TransactionAdditionalFilters conservando modelos existentes.
3. Validar interacción y contratos, ejecutar verificaciones y guardar commit local.
