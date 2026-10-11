# Gastos personales sin aportaciones pendientes

## Contexto y objetivo

El ledger aplica reglas de déficit compartido a gastos personales. Un saldo negativo personal debe representar el déficit sin generar aportaciones pendientes del usuario consigo mismo.

## Alcance y comportamiento esperado

Los nuevos movimientos guardan si se registraron con reglas compartidas según los miembros actuales. Con un solo miembro los gastos son personales, incluso si la cuenta estuvo compartida. Reducen saldo y ledger por el importe completo sin reparto, deuda, aportaciones pendientes ni ingresos técnicos. Las obligaciones compartidas anteriores se conservan.

## Fuera de alcance

Reparación o reclasificación masiva de datos existentes, cambios en las reglas compartidas y ejecución remota.

## Decisiones técnicas

Añadir transactions.uses_shared_account_rules boolean nullable. Generar migración con Artisan. Backend calcula el valor; null conserva comportamiento legacy. Sin backfill. Conservar clasificación al editar en la misma cuenta; resolver nuevamente al mover explícitamente a otra cuenta. Usar la instancia bloqueada persistida al editar. Los cambios de membresía no reclasifican movimientos anteriores. No depender del catálogo de categorías para decidir reglas financieras.

## Backend y persistencia

Conservar entradas de custodia de ingresos. Gastos personales generan account_fund_expense negativo completo y no generan asignaciones, déficit, reembolsos o recuperación técnica. Fuente account_fund y pagador personal resueltos en servidor; al editar después de compartir conservar su pagador original. Compartidos y legacy conservan sus flujos actuales.

## Frontend y componentes afectados

Reutilizar TransactionsForm y controles App existentes. Tipar y mapear usesSharedAccountRules nullable. El formulario conserva las condiciones financieras de movimientos compartidos anteriores aunque quede un miembro y mantiene gastos personales anteriores como personales al compartir después.

## API, validaciones y permisos

Campo de lectura derivado, nunca controlado por el cliente. Mantener autorización de cuentas y autoría de transacciones. Actualizar OpenAPI y documentación de dominio. Preservar rutas y contratos existentes.

## Casos límite

Saldos cero/negativo, gasto que cruza a déficit, edición y eliminación, cuenta que vuelve a un miembro, cuenta personal que se comparte, traslado de movimiento y registros legacy con indicador null. No cambiar ingresos reales ni obligaciones históricas por cambios de membresía.

## Estrategia de pruebas y validación

Pruebas backend de saldos, ledger, ausencia de deudas y recuperaciones, clasificación persistente, transición de membresía, traslado, permisos y compatibilidad legacy. Regresiones de custodia/reparto/reembolsos compartidos. Pruebas Vue de normalización y conservación del formulario; ejecutar suites Laravel/Vue, TypeScript, build y ESLint dirigido. Comprobar migración nullable sin backfill y diferencias limpias.

## Plan de implementación y commits

1. Añadir pruebas de comportamiento y migración/indicador servidor.
2. Separar registro de gastos personales del procesamiento compartido conservando ledger.
3. Alinear lectura/formulario y regresiones.
4. Actualizar dominio/contrato y validar integración.
5. Commits convencionales en inglés de backend, frontend y documentación cuando sea coherente.

## Archivos estimados a modificar

Modelo Transaction y orquestadores de creación/edición/ledger/recuperación; migración; pruebas API. Tipos/esquemas/formulario Vue y pruebas. Documentación de cuentas compartidas y OpenAPI.

## Riesgos y preguntas abiertas

Evitar reclasificación implícita o pérdida de condiciones históricas al editar y cambios de reparto compartido. Ninguna pregunta abierta: sin reparación histórica; nuevos gastos con un miembro personales.
