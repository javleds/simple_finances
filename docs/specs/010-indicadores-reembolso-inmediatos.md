# Indicadores de reembolso inmediatos

## Contexto y objetivo
Los listados hidratan los importes Por pagar y Por recibir desde el ledger, pero las respuestas de creación y edición no lo hacen. La SPA inserta esos movimientos sin indicadores hasta recargar.

## Alcance y comportamiento esperado
Incluir los importes del usuario autenticado en creación, edición y consulta individual de transacciones, tanto en rutas anidadas como generales. Conservar las respuestas de creación que devuelven una colección por movimientos técnicos.

## Decisiones técnicas y componentes afectados
Reutilizar HydrateCurrentUserPendingReimbursements en los controladores de transacciones después de obtener la instancia definitiva y antes de serializar. No ejecutar fresh después de hidratar: perdería los atributos calculados.

## Backend, API y persistencia
Añadir los mismos campos de lectura ya consumidos por Vue, sin cambiar el ledger, esquema, permisos ni cálculos. Las colecciones se hidratan en lote.

## Frontend y validaciones
Conservar el mapeador, la inserción y el reemplazo existentes: ya consumen ambos importes. Probar el contrato que reciben estos flujos.

## Permisos y seguridad
Calcular siempre para el usuario autenticado; no para el autor o pagador implícitamente.

## Casos límite y riesgos
Autor diferente del pagador, importes cero en cuentas personales, edición de importe y creación con movimiento técnico asociado. Evitar perder atributos al refrescar el modelo.

## Estrategia de pruebas y validación
Regresión API: creación y edición devuelven inmediatamente importes iguales al listado para pagador y deudor; consulta individual coherente. Laravel dirigido y suite completa.

## Estrategia de commits
Un commit de corrección y pruebas.

## Archivos estimados a modificar
AccountTransactionController, TransactionController y pruebas API de indicadores.

## Fuera de alcance
Reclasificar movimientos o cambiar reglas de reembolso. Sin migraciones ni cambios de interfaz.

## Preguntas abiertas
Ninguna.

## Plan de implementación
1. Reproducir ausencia de indicadores en respuestas de escritura con pruebas API.
2. Hidratar todas las respuestas afectadas usando el servicio existente y conservar tipos de respuesta.
3. Ejecutar regresiones y suite Laravel; guardar commit local.
