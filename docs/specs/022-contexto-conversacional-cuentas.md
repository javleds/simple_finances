# Contexto conversacional de cuentas

## Contexto
El historial y los checkpoints no mantienen una selección explícita entre ejecuciones. La pantalla puede desplazar referencias conversacionales.
## Objetivo
Persistir cuenta activa, candidatos y pregunta pendiente por thread; aclarar referencias ambiguas y retomar la consulta tras elegir.
## Alcance
Resolución estructurada con LangChain, validación MCP, persistencia atómica PostgreSQL y etiqueta de contexto en el panel existente.
## Fuera de alcance
Nuevas herramientas de ingresos/abonos; modificar finanzas; usar claves reales durante desarrollo.
## Comportamiento esperado
Una cuenta explícita única sustituye la activa. Una referencia singular usa la activa salvo que el último alcance sea colectivo. Tras hablar de varias cuentas se pregunta cuál; se conserva la consulta original. La respuesta a la aclaración selecciona la cuenta y retoma la consulta. Pantalla solo inicializa conversaciones sin selección; navegar no cambia la selección. Global explícito abandona la selección para esa consulta y deja ambigüedad singular posterior.
## Decisiones técnicas
Estado tipado en JSON de agent_threads, separado del historial truncado. Un paso de resolución previo a herramientas financieras interpreta intención con salida estructurada; el código resuelve nombres y valida identificadores con MCP. No elegir por orden de resultados ni reducir una selección colectiva a la única cuenta que siga autorizada. Preguntas aclaratorias deterministas. Cambios se guardan junto con la respuesta solo al completar el run.
## Componentes afectados
Agente: schemas/contexto, grafo, worker, store, migración Alembic, API y pruebas. Finance: repositorio/composable/panel de chat.
## Backend
El modelo propone alcance y referencias; solo IDs recordados o resueltos por MCP son válidos. Estado recordado se reautoriza en cada turno. Fallos de dependencia no equivalen a revocación. Se inyecta el alcance resuelto al grafo y se impide consultar otra cuenta sin resolver de nuevo.
## Frontend
Reutilizar AppText en ChatPanel, sin nuevos controles PrimeVue. Mostrar cuenta activa, conjunto o selección pendiente desde estado autorizado del servidor; restaurar al abrir el historial.
## Persistencia
Migración aditiva 003: JSON nullable para conversaciones existentes; vacío significa contexto no inicializado. Sin backfill automático ambiguo. Borrado del thread elimina también este estado. Rollback elimina solo el nuevo contexto, no mensajes.
## API
Campos aditivos conversation_context en historial y mensajes completados; solo estado público, nunca pregunta pendiente interna. Mantener gateway y autenticación actuales.
## Validaciones
Listas acotadas, IDs positivos, sin identidad elegida por el modelo. Candidatos incompletos no habilitan selección automática. Cancelación/fallo no cambia la selección persistida.
## Permisos y seguridad
Actor y canal siguen controlados por servidor. Revalidar cuentas antes de exponer nombres/contexto. No reusar pregunta pendiente si sus referencias dejaron de estar autorizadas.
## Casos límite
Homónimos, cuenta inexistente, selección ordinal, varias candidatas, acceso revocado, cambio de pantalla, historial truncado, reapertura, cambio de tema, nuevo chat y cancelación.
## Estrategia de pruebas
Reasoners y MCP simulados: secuencias de varios turnos, persistencia entre instancias, ambigüedad, retomar pregunta, aislamiento de threads y permisos. Prueba HTTP de esquema OpenRouter con credencial ficticia. PostgreSQL temporal para migración. UI: restauración/actualización de etiqueta.
## Estrategia de validación
pytest, Ruff, mypy, Vitest focalizado, type-check y build. OPENROUTER_API_KEY ficticia y tracing deshabilitado en desarrollo; cualquier uso real exclusivamente en E2E con MCP y datos de prueba.
## Estrategia de commits
1. feat(agent): persist and resolve conversational account context
2. feat(chat): display persistent conversation account context
## Archivos estimados a modificar
apps/agents/src/finance_agent, migrations/versions, tests, evaluations/cases.json; apps/finance/resources/js/modules/chat; docs/operations/assistant.md.
## Riesgos
Una llamada adicional al modelo aumenta latencia. La extracción semántica depende del modelo; autorización y transiciones se validan en código. Desplegar migración antes de API/worker.
## Preguntas abiertas
Ninguna bloqueante. Referencia singular tras alcance colectivo requiere aclaración incluso si solo queda un candidato visible.
## Plan de implementación
1. Crear estado tipado/migración y persistencia atómica, probar fallo y cancelación.
2. Implementar interpretación/resolución y límites de consulta, probar secuencias simuladas sin credencial real.
3. Exponer estado autorizado y mostrarlo en panel, probar reapertura y actualización.
4. Ejecutar verificaciones, documentar operación y guardar commits.

## Resultado de implementación

Implementado el estado persistente, resolución estructurada, preguntas de aclaración y reanudación, restricción de herramientas por alcance, reautorización y etiqueta del panel. Los mensajes completados incorporan contexto público; se redacta también en SSE al revocar acceso. La pantalla ignorada no contamina las referencias de autorización. La selección se publica solo al completar el run.

Validación: 85 pruebas Python aprobadas, incluidas PostgreSQL real y secuencias con worker/modelo simulado; 35 pruebas Vue del chat; Ruff, mypy, ESLint, type-check y build. Upgrade/downgrade de la migración verificado en base temporal conservando conversaciones. Revisión independiente sin bloqueos pendientes. No se utilizó la clave real ni se ejecutaron evaluaciones contra un modelo real: la interpretación semántica queda cubierta por contrato simulado y los nuevos casos conversacionales documentados para E2E.
