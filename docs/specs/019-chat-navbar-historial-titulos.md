# Chat contextual desde el navbar con historial y títulos por IA

Estado: especificación para implementación; este documento no implementa el feature.

## Contexto

La especificación 015 incorporó un chat base. Hoy `ChatLauncher.vue` dibuja un botón flotante únicamente si el meta `chat-enabled` es true. `ChatPanel.vue` usa `AppModal`, un selector por fecha y un botón Nueva. `useChat` ya integra threads, historial paginado, runs, SSE, recuperación, cancelación y contexto por ruta. FastAPI persiste threads UUID en PostgreSQL y el worker usa ese identificador como `thread_id` de LangGraph. No existen títulos de conversación en el modelo ni en el contrato.

El usuario solicita evolucionar esta experiencia conforme a las dos capturas aportadas. Su indicación final reemplaza la propuesta inicial de botón flotante: el acceso debe estar siempre en el navbar. Las anotaciones representan controles y distribución; el fondo blanco y las líneas rojas no son estilos del producto.

## Objetivo

Abrir y gestionar conversaciones desde la pantalla actual de Finance, con un botón de navbar en desktop y móvil, un panel superpuesto desktop y una vista de pantalla completa móvil. Navegar entre conversación e historial dentro del mismo contenedor, con títulos inferidos por IA e identidad persistente por thread.

## Alcance

- Acceso en navbar, junto al selector de tema y antes del menú de usuario, en ambas variantes responsive.
- Panel con dos vistas internas: conversación e historial.
- Listado de threads con título, ID y fecha; selección, paginación y nueva conversación.
- Generación y persistencia del título mediante el modelo existente de OpenRouter/LangChain.
- Conservación de contexto, autorización, idempotencia, recuperación SSE, render seguro y carga diferida.
- Documentación, migración PostgreSQL y pruebas del flujo completo.

## Fuera de alcance

Integración WhatsApp, búsqueda de conversaciones, renombrado manual, eliminar/archivar chats, carpetas, adjuntos, voz, cambios en cálculos financieros, publicación de FastAPI/MCP o reemplazo del gateway Laravel. No se conectará el navegador al servidor dev de LangGraph en 2024.

## Comportamiento esperado

### Acceso y disponibilidad

- Un único botón de chat en el navbar autenticado, con Heroicon, etiqueta accesible «Abrir chat», `aria-expanded` y asociación al panel. No hay botón flotante ni nueva entrada en la navegación inferior.
- El botón se muestra tanto en desktop como en móvil. `CHAT_ENABLED=false` ya no lo oculta: al abrir, muestra «El asistente aún no está habilitado» sin consultar endpoints del chat. No se cambia el valor del flag ni la protección del backend automáticamente.
- Con el chat habilitado, abrir carga el módulo diferido y consulta historial; nunca navega a otra ruta, crea un thread por sí solo ni modifica filtros de la página.
- Reabrir en la misma sesión recupera la conversación, vista y borrador en memoria. Recargar la página puede iniciar una vista nueva; las conversaciones guardadas siguen accesibles en Historial. Logout elimina todo el estado local.
- Un fallo de red muestra una acción de reintento dentro del panel; no debe cerrar sesión ni romper Finance.

### Desktop (ancho de viewport >= 1024 px)

- Ventana superpuesta fija respecto al viewport, anclada abajo a la derecha, sin desplazar el contenido ni navegar. El requisito de «elemento absoluto» se implementa como overlay con posición fija para no depender del scroll del dashboard.
- Tamaño inicial: ancho 440 px, altura 640 px, márgenes 24 px; limitar ambos al espacio disponible. No permitir que quede debajo del navbar en ventanas bajas.
- Sin máscara que impida usar la página; el usuario puede cambiar de cuenta mientras mantiene abierto el chat. No cerrar al hacer clic fuera. Cerrar mediante X, Escape con foco dentro del panel o el propio botón de navbar.
- Encabezado y compositor permanecen visibles; solo el transcript o listado desplaza. Sin arrastre ni redimensionado en este alcance.

### Móvil y tablet (ancho < 1024 px)

- Mismo botón de navbar y mismo estado de conversación; abrir ocupa todo el viewport, sobre navbar y navegación inferior.
- Seguir la presentación fullscreen de las modales de creación existentes: superficie y tokens del producto, safe areas, bloqueo del scroll de fondo y foco contenido en el diálogo.
- Usar altura dinámica del viewport; verificar teclado virtual y orientación para que compositor y cerrar permanezcan accesibles.
- Cambiar de tamaño no desmonta el chat ni pierde mensaje, thread o petición en curso.

### Vista conversación

Encabezado: botón Historial a la izquierda, Nuevo chat junto a él, título de la conversación en el espacio central y Cerrar a la derecha. Nuevo chat está disponible también al ver un chat anterior. Mostrar debajo el ID completo en un área secundaria que pueda partir líneas, y el contexto vigente del próximo mensaje (global o cuenta).

Mensajes del agente a la izquierda y del usuario a la derecha. Conservar `ChatMessageContent`, referencias autorizadas y avisos de resultados parciales. El compositor queda abajo; Enter envía, Shift+Enter inserta una línea e IME no envía prematuramente. Mantener límite de 4000 caracteres y protección contra doble envío. Texto multilínea con componente PrimeVue, estados de envío y botón Detener para el run activo.

Si no existe conversación seleccionada, mostrar estado «Nuevo chat» con sugerencias textuales simples; el primer envío crea el thread antes de crear el run. No habilitar envío financiero cuando la creación del thread falla.

### Vista historial

- Sustituye el 100% del contenido de la ventana, también en desktop. No es un dropdown ni una columna simultánea al transcript.
- Encabezado: Volver a conversación, Nuevo chat y Cerrar. El compositor no se muestra en esta vista.
- Lista desplazable: título como texto principal, ID del thread secundario y fecha de creación. ID completo disponible en la fila sin desbordar; título máximo dos líneas visuales.
- Estado activo identificado visualmente y de forma accesible. Seleccionar una fila carga mensajes de ese thread, cambia a conversación y conserva el mismo UUID al continuar.
- Reutilizar paginación actual de 30 threads y cursor opaco; mantener orden por `created_at DESC, id DESC`. No introducir orden por actividad sin rediseñar cursor.
- Reutilizar `AppListState`, `AppEmptyState` y `AppLoadMoreFooter` para carga, vacío, error y más resultados. No tratar fallo de carga como lista vacía.

### Nuevo chat, identidad y recuperación

- Pulsar Nuevo chat crea inmediatamente un thread por `POST /api/chat/threads`, con `client_request_id` UUID de idempotencia. FastAPI es la autoridad del nuevo `id`; la SPA no inventa el thread_id ni reutiliza el anterior.
- Tras respuesta correcta: seleccionar el nuevo ID, insertar la fila y mostrar compositor vacío con título provisional «Nueva conversación». Puede aparecer en historial aunque aún no tenga mensajes.
- Bloquear doble clic mientras se crea; si la respuesta se pierde, reintentar con el mismo `client_request_id`. Una acción nueva posterior a una creación confirmada usa otra clave.
- Preservar la conversación anterior hasta confirmar la nueva. Si la creación falla, mostrar error recuperable sin perder el borrador anterior.
- Para el primer envío desde el estado sin thread, reutilizar la misma operación de creación; un mensaje no puede provocar dos threads.
- Cerrar panel, abrir historial o seleccionar otro thread no cancela un run. Desconectar la suscripción de la vista anterior, conservar identidad de la operación y recuperar estado mediante historial/polling al volver.
- Desacoplar el bloqueo actual `busy` de la navegación. Se puede consultar otro chat mientras trabaja el anterior; no permitir dos runs activos en un mismo thread. Las respuestas tardías nunca se insertan en el thread seleccionado después.
- Conservar borradores por thread en memoria de sesión, incluido el borrador sin thread. No guardar mensajes financieros ni tokens en almacenamiento nuevo del navegador.

## Decisiones técnicas

### Reutilización PrimeVue

Proyecto confirmado: Vue 3, TypeScript y PrimeVue 5. Reutilizar `AppIconButton`, `AppButton`, `AppText`, `AppLink`, estados de listas y `ChatMessageContent`.

`AppModal` ya envuelve PrimeVue Dialog pero fuerza modal, bloqueo de fondo y un header propio. Ampliarlo de forma compatible con propiedades de modalidad/posición y slot de encabezado opcional para este caso; mantener sus defaults para todas las modales existentes. La presentación de chat usa un único Dialog, modal en móvil y no modal en desktop. Ajustar el breakpoint de esta presentación a 1024 px sin cambiar el de las modales actuales. El modo historial es contenido interno del mismo Dialog, no otro overlay.

Metadatos PrimeVue consultados: Dialog admite `position`, `modal`, `blockScroll`, `draggable`, `appendTo`, encabezado/pie y tamaño responsive; el modo no modal conserva el tabulado de página y el modal contiene el foco. Fuente MCP: `primeui://primevue/components/dialog.md`, documentación [Dialog](https://primevue.dev/dialog). Antes de implementar, confirmar con MCP el ejemplo de posición y los metadatos de Textarea y de cualquier control adicional; ejecutar `validate_usage` sobre el código final. No inventar APIs ni añadir librerías de chat.

Colores, bordes, radios y sombras del tema existente; compatible con claro/oscuro. Mantener `defineAsyncComponent` y evitar cargar repositorio, panel y controles pesados antes de abrir.

### Generación de títulos

- Título de 3–8 palabras, español, una línea lógica y hasta 80 caracteres; describir intención («Gastos de enero por categoría»), no cifras privadas ni identificadores de cuenta.
- Ampliar la salida estructurada de la respuesta final del grafo con `conversation_title` opcional. Solicitarlo cuando el thread no tenga un título generado; usa la primera interacción autorizada. No requiere herramientas adicionales ni otra llamada al modelo solo para titular.
- La respuesta financiera continúa siendo obligatoria y validada. Un título ausente/inválido no convierte una respuesta correcta en fallo: validarlo separadamente, ignorarlo si incumple límites y conservar fallback.
- Persistir una sola vez el primer título válido, junto con las referencias autorizadas que sustentaron esa respuesta. No cambiar el título con cada mensaje ni confiar en referencias generadas por el modelo.
- Si el modelo no produce título, mostrar «Conversación del {fecha}». No presentar un recorte del mensaje como título inferido. En siguientes respuestas de un thread sin título puede intentarse nuevamente, sin tarea independiente.
- No guardar títulos derivados de respuestas fallidas, canceladas o rechazadas por revocación. La escritura de mensaje final y título debe respetar la transición terminal y la exclusión concurrente existentes.
- Tras completar el run, refrescar el listado para obtener el título persistido. Al abrir historial también se refresca. No añadir un nuevo evento SSE obligatorio en esta fase.

## Componentes afectados

| Área | Cambio |
| --- | --- |
| AdminLayout / ChatLauncher | Botón dentro del navbar, disponibilidad y carga diferida |
| ChatPanel | Contenedor responsive y alternancia conversación/historial |
| ChatConversationList (nuevo, local al módulo) | Filas de threads, selección y paginación |
| useChat / chatRepository | Creación explícita, estado por conversación y nuevos campos |
| AppModal | Extensión compatible para overlay no modal desktop y header configurable |
| FastAPI / Store / worker / graph | Persistir y serializar título autorizado |
| PostgreSQL | Migración de metadatos del thread |
| Gateway Laravel | Mantener proxy; ampliar pruebas de contrato |

## Backend

Reutilizar rutas `/api/chat` de Finance y `/internal/v1` de FastAPI, `SendAgentRequest`, `Store.create_thread`, `Store.list_threads`, `Store.finish`, `visible_messages` y `worker.execute`. No crear un segundo backend de chat ni duplicar autorización en Vue.

En el worker, consultar si falta título al preparar la ejecución; aceptar un candidato del grafo y guardarlo condicionalmente solo si sigue sin título y el run termina correctamente. Adaptar `thread_data` y la respuesta del listado. La sanitización por acceso se realiza en la capa API con las herramientas autorizadas, antes de retornar títulos.

## Persistencia

Migración Alembic siguiente a `001_conversations`:

- `agent_threads.title`: string nullable, máximo 80 caracteres.
- `agent_threads.title_references`: JSON de referencias de origen, vacío por defecto.

Threads existentes siguen válidos con title null; no backfill mediante llamadas al proveedor. Reutilizar `id`, `owner_user_id`, `channel`, fechas y retención existentes. No añadir tablas MySQL ni migraciones Laravel. Las nuevas columnas son aditivas; rollback funcional puede volver al cliente anterior manteniéndolas.

## API

Se conservan actor derivado en Finance, bearer interno, channel e idempotencia existentes.

| Operación pública | Efecto |
| --- | --- |
| `POST /api/chat/threads` | Crear/repetir thread idempotente; devuelve metadatos |
| `GET /api/chat/threads?cursor=...` | Lista paginada con título visible para ese actor |
| `GET /api/chat/threads/{id}/messages?cursor=...` | Historial autorizado y run activo |
| Rutas existentes de runs, events y cancel | Envío, recuperación y cancelación sin cambios de semántica |

Metadatos de thread, internos como JSON directo y públicos bajo `data`:

```json
{
  "id": "UUID-del-thread",
  "title": "Gastos de enero por categoría",
  "created_at": "2026-11-04T06:00:00Z",
  "updated_at": "2026-11-04T06:02:00Z"
}
```

`title` puede ser null por ausencia o falta de acceso a sus referencias; el cliente aplica fallback genérico. `title_references` no se expone como contenido de historial. Actualizar Zod para campos aditivos y aceptar su ausencia durante despliegues escalonados. `items` y `next_cursor` conservan forma.

El ID devuelto es el que utiliza FastAPI para ownership, mensajes y runs, y el worker como `configurable.thread_id` de LangGraph; `run_id`/checkpoint namespace no son conversaciones nuevas. Studio y su estado local no intervienen en este protocolo.

## Validaciones

- UUID de creación/request/thread, ownership por actor y canal y contexto de cuenta validados por backend.
- Contexto por mensaje: listado/global fuera de cuenta; `accountId` en cuenta y subrutas. Volver a un chat viejo no fija la cuenta antigua como contexto del siguiente mensaje.
- Título validado como texto simple, sin HTML, markup, saltos de línea ni enlaces; limitar tamaño de entrada antes de persistir. La UI renderiza siempre como texto.
- Errores diferenciados de historial, creación, envío y recuperación; no vaciar datos válidos ante un error transitorio.
- No llamar APIs ni generar conversaciones al abrir un chat deshabilitado.

## Permisos y seguridad

Mantener FastAPI/MCP privados. Identidad exclusivamente desde el guard de Finance. El nuevo título también es información potencialmente privada: al listar o retornar thread, revalidar sus referencias con las mismas reglas de acceso que los mensajes; si alguna ya no es visible, devolver title null sin transmitir el texto original. Reutilizar el helper de visibilidad con caché de autorización por solicitud para evitar repetir consultas por referencia. Fallos de MCP no habilitan mostrar títulos sin verificar.

No usar títulos para instrucciones del agente, contexto confiable o autorización. Tratar prompt injection dentro de mensajes/títulos como datos. Logout limpia mensajes, borradores, cursores y suscripciones; no reaparecen por promesas tardías de otro usuario.

## Casos límite

- Historial vacío, thread vacío, thread legado sin título y título largo/inválido.
- Doble clic en Nuevo chat, respuesta HTTP perdida y reintento de primer mensaje.
- Run en curso mientras se cambia de conversación, se cierra panel o se abre historial.
- SSE interrumpido, respuesta tardía, cancelación y conversación seleccionada distinta.
- Cambiar de cuenta con chat abierto; petición explícita puede anular contexto de pantalla.
- Revocación de cuenta tras generar título y después de haber cargado historial; refrescar y limpiar título al recibir denegación.
- Navegación rápida, logout, múltiples pestañas y cursor agotado.
- Viewport bajo, teclado móvil, rotación, títulos/IDs largos y lector de pantalla.

## Estrategia de pruebas

### Python/PostgreSQL

Migración con threads previos; título válido/nulo/inválido; escritura una sola vez; cancelación o fallo no persisten candidato; idempotencia no duplica threads; ownership y canal; título oculto tras revocación; proveedor sin título no impide completar respuesta. Verificar UUID compartido con LangGraph y referencias fuera del control del modelo. Usar dobles para CI y fixtures ficticios para evaluar títulos reales con OpenRouter por separado.

### Laravel

Extender `ChatGatewayTest`: campos nuevos conservados, identidad forjada rechazada, errores de upstream y flag deshabilitado. No modificar rutas para acceder directamente al agente.

### Vue

Launcher en navbar; disponibilidad deshabilitada; abrir no crea thread; acción Nuevo crea una sola vez y reintenta con misma clave; seleccionar conserva ID; historial sustituye transcript; títulos/fallback/ID; paginación; borradores por thread; cambio durante run sin mezclar respuestas; cerrar/reabrir; logout; navegación de contexto; mensajes vacíos y Enter/IME.

### Navegador

Desktop 1440×900 y 1024×768; móvil 390×844 y 320×640. Verificar esquina inferior derecha sin bloquear página, fullscreen real sin barra inferior visible, historial ocupando toda ventana, foco restaurado al navbar, Escape, navegación por teclado, contraste claro/oscuro y compositor visible con teclado móvil. Revisar red: solo Finance como endpoint de la SPA y ausencia de cargas pesadas antes de abrir.

## Estrategia de validación

Por fase: pytest relevante, Ruff, mypy; test Laravel del gateway; Vitest del chat y modales; `npm run type-check`, lint sobre archivos tocados y `npm run build`. Validar API PrimeVue con MCP en código final y comprobar que modales de creación existentes conservan comportamiento. Integración completa con proveedor simulado y bases ficticias; luego evaluación de títulos reales cuando haya configuración del proveedor. Documentar resultados y limitaciones, sin atribuir calidad del modelo a pruebas simuladas.

## Estrategia de commits

1. `feat(agent): persist authorized AI conversation titles` — migración, grafo/worker/store/API y pruebas.
2. `feat(chat): manage explicit thread creation and conversation history` — contrato, estado y pruebas de concurrencia/recuperación.
3. `feat(ui): open responsive chat from the navbar` — extensión de AppModal, navbar, vistas, PrimeVue y pruebas visuales.
4. `docs(chat): document navbar assistant and validation` — operación, arquitectura y resultados de aceptación. Actualizar documentación relevante también en los commits anteriores cuando cambie el contrato.

## Archivos estimados a modificar

- `apps/finance/resources/js/modules/admin/layouts/AdminLayout.vue`.
- `apps/finance/resources/js/modules/chat/components/{ChatLauncher,ChatPanel,ChatConversationList}.vue` y pruebas.
- `apps/finance/resources/js/modules/chat/{composables/useChat.ts,repositories/chatRepository.ts}` y pruebas.
- `apps/finance/resources/js/modules/shared/components/AppModal.vue` y pruebas; `resources/js/AGENTS.md` si cambia su API documentada.
- `apps/agents/src/finance_agent/{models,store,api,worker,graph}.py`, migración Alembic y tests.
- `apps/finance/tests/Feature/Api/ChatGatewayTest.php`.
- `docs/operations/assistant.md`, `docs/frontend/primevue.md` y esta especificación.

## Riesgos

Cambiar AppModal puede afectar formularios existentes: conservar defaults y verificar regresión. Títulos podrían filtrar referencias revocadas si solo se protege el transcript: aplicar autorización también a metadatos. El estado actual de envío mezcla espera de SSE y busy: separar navegación sin perder idempotencia. El navbar móvil tiene poco espacio: mantener controles compactos accesibles sin quitar tema/perfil. No sobrecargar el bundle inicial con el panel.

## Preguntas abiertas

No hay decisiones bloqueantes. Se adoptan: breakpoint 1024 px, historial por fecha de creación, título único inferido al primer intercambio exitoso y botón visible con estado no disponible cuando CHAT_ENABLED está desactivado. Cualquier cambio de estas decisiones se registra antes de implementar.

## Plan de implementación

| Paso | Propósito y cambios | Dependencia | Validación / salida |
| --- | --- | --- | --- |
| 1 | Ampliar Thread y Alembic; conservar threads sin título y serialización aditiva. | Contrato de este spec. | Migración y pruebas de metadatos/ownership. |
| 2 | Añadir candidato de título en salida del grafo, persistencia condicional y revalidación en API. | Paso 1. | Títulos válidos, fallos no fatales y revocación probados. |
| 3 | Adaptar repositorio/useChat: creación explícita idempotente, borradores, selección y suscripciones independientes. | API aditiva. | Pruebas de carreras, run activo y recuperación sin duplicados. |
| 4 | Extender AppModal compatible; mover launcher al navbar; dividir conversación/historial con PrimeVue. | Paso 3. | Tipos, validación PrimeVue y modales existentes intactas. |
| 5 | Integrar flujo responsive, estados, foco, teclado y actualización de títulos. | Pasos 2–4. | Recorrido desktop/móvil y gateway → agente → MCP completo. |
| 6 | Actualizar docs y registrar validación, tamaños del bundle y pendientes externos. | Checks anteriores. | Commits revisables y aceptación documentada. |

## Criterios de aceptación

1. El botón solo vive en el navbar y abre el chat sin cambiar la ruta.
2. Desktop muestra overlay inferior derecho; móvil usa toda la pantalla con el mismo contenido.
3. Historial y conversación son vistas exclusivas dentro del panel; cerrar historial vuelve al chat sin perderlo.
4. Cada fila tiene ID, título generado o fallback y fecha; seleccionar continúa el mismo thread.
5. Nuevo chat obtiene un UUID nuevo de FastAPI; reintentar la misma creación no lo duplica.
6. El título válido se genera por IA y persiste; su fallo no bloquea la respuesta financiera.
7. Ningún título o mensaje revela datos de otro actor o de referencias revocadas.
8. Cerrar, cambiar vistas o seleccionar otra conversación no cancela ni mezcla runs.
9. Se mantienen contexto por ruta, seguridad del gateway, carga diferida y accesibilidad.
10. Pruebas, documentación y verificaciones visuales demuestran el resultado antes de integrar a main.
