# Borrado físico de conversaciones

## Contexto
El historial permite crear y consultar conversaciones, pero no eliminarlas. PostgreSQL del agente contiene threads, mensajes, runs, eventos y checkpoints de LangGraph. Finance actúa como gateway y MCP consulta MySQL sin persistir conversaciones. Debugbar de Finance puede conservar capturas de solicitudes en desarrollo.

## Objetivo
Eliminar físicamente una conversación propia desde el historial y retirar su estado de la interfaz, sin borrar los datos financieros consultados.

## Alcance
DELETE autenticado en Finance/FastAPI, purga de persistencia del agente, control de concurrencia con workers, limpieza del estado Vue y confirmación accesible con componentes existentes. Evitar nuevas capturas Debugbar del chat y limpiar capturas locales relacionadas cuando corresponda.

## Fuera de alcance
Eliminar cuentas/transacciones, backups históricos o registros retenidos por OpenRouter/LangSmith. Studio mantiene threads de desarrollo independientes. No implementar soft delete ni un historial de conversaciones borradas.

## Comportamiento esperado
Cada fila del historial tiene Eliminar. Una confirmación explica que el borrado es permanente. Solo se retira la fila tras éxito; la conversación activa se limpia y queda un nuevo chat sin ID. Una respuesta en curso impide el borrado con 409; se puede detener y reintentar cuando el worker haya liberado la conversación. Las otras conversaciones y sus borradores permanecen.

## Decisiones técnicas
DELETE /api/chat/threads/{uuid} → DELETE /internal/v1/threads/{uuid}. Finance deriva actor y canal; FastAPI verifica propietario y canal sin depender de permisos financieros vigentes para permitir eliminar datos propios. Finance verifica primero propiedad con HEAD interno (actor en query), limpia capturas locales y después solicita DELETE. Así un fallo local conserva el thread para reintentar y una respuesta perdida del DELETE no deja copias locales. HEAD no consulta MCP ni retorna mensajes. Respuesta 204; 404 indistinguible para inexistente/ajena, 409 ocupada, 503 dependencia no disponible. El cliente trata 404 como ya ausente al reintentar.

El borrado del agente debe abarcar agent_threads, agent_runs, agent_messages, agent_events y tablas LangGraph del thread. Bloquear escritores concurrentes mediante coordinación PostgreSQL por thread, incluida toda la duración de ejecución/checkpointing del worker. No responder éxito si la purga falla. Preferir una transacción única de PostgreSQL sobre tablas conocidas del checkpointer instalado; sin migración si el esquema actual basta.

## Componentes afectados
Store/API/worker y tests Python; gateway/rutas/validación/tests Laravel; repositorio, composable y lista de conversaciones Vue; guías operativas.

## Backend
Controlador delega al servicio existente de HTTP. MCP conserva solo lectura y no recibe herramientas de borrado financiero.

## Frontend
Reutilizar AppButton, AppIconButton, AppModal y los patrones PrimeVue existentes. Evitar botones anidados. Errores de eliminación permanecen en la confirmación y no descartan conversaciones.

## Persistencia
Sin nuevas entidades financieras. Auditar tablas del checkpointer real y cascadas. Verificar físicamente ausencia de todos los registros asociados y conservación de threads ajenos.

## API
Validar UUID, actor positivo, canal y service token. Mantener identidad elegida exclusivamente por Finance. Evitar respuestas con contenido borrado.

## Validaciones
Bloquear doble envío y envíos a una conversación en proceso de eliminación. Invalidar solicitudes anteriores para que historial/SSE no repueble el estado eliminado.

## Permisos y seguridad
Propiedad de conversación y canal son obligatorios. Revocación de acceso a una cuenta no impide eliminar una conversación propia. Borrar no autoriza modificar el dominio financiero. Documentar límites de backups/proveedores externos.

## Casos límite
Hilo activo o cancelándose, retries tras respuesta perdida, hilo eliminado en otra pestaña, historial paginado, borrado de hilo seleccionado/no seleccionado, respuesta tardía de red y fallo de checkpoints.

## Estrategia de pruebas
Pruebas de autorización, cascadas, checkpoints reales en PostgreSQL, rollback y carreras con worker. Gateway: actor derivado y propagación de 409/404. Vue: confirmación/cancelar, error, doble clic, seleccionado y no seleccionado, respuestas tardías.

## Estrategia de validación
pytest/Ruff/mypy, PHPUnit focalizado, Vitest/type-check/ESLint/build y Chrome DevTools con una conversación creada exclusivamente para esta prueba.

## Estrategia de commits
1. feat(agent): physically delete owned conversations safely
2. feat(chat): expose conversation deletion through Finance
3. feat(ui): delete conversations from chat history
Documentación alineada con cada bloque.

## Archivos estimados a modificar
apps/agents/src/finance_agent/{api,store,worker}.py y tests; apps/finance/app/Http, routes/api.php, recursos chat y tests; configuración Debugbar y docs/operations/assistant.md.

## Riesgos
Un worker anterior al despliegue no participa en el bloqueo nuevo; detener workers al desplegar. Cambios futuros del esquema LangGraph requieren revisar la purga. Logs externos/backups no forman parte de la base activa y no pueden prometerse eliminados por esta API.

## Preguntas abiertas
Ninguna bloqueante; rechazo temporal de borrado mientras el thread está ocupado conserva la integridad.

## Plan de implementación
1. Implementar transacción y coordinación de checkpoints con pruebas de PostgreSQL.
2. Añadir gateway autenticado y revisar capturas locales de desarrollo.
3. Añadir confirmación y limpieza de memoria Vue, protegiendo respuestas tardías.
4. Verificar integración real con hilo de prueba, actualizar documentación y guardar commits.

## Resultado
Implementado y revisado. Validación aprobada: 67 pruebas Python con PostgreSQL, 32 Vue y 21 Laravel; análisis estático y build. La integración local confirmó DELETE 204 y ausencia física de filas en threads, mensajes, runs, eventos, checkpoints, blobs y writes de la base aislada. No se modificaron transacciones financieras.
