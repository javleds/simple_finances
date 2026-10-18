# Documentos legales y aceptación versionada

## Contexto

Meta solicita que el sitio público identifique al titular legal. Los documentos actuales son genéricos y las casillas del registro no conservan evidencia persistente.

## Objetivo

Publicar documentos adaptados a Fin-si y conservar aceptación explícita verificable, incluyendo usuarios existentes.

## Alcance

Identidad configurable en Laravel, páginas públicas legibles sin JavaScript, términos, privacidad, consentimiento financiero y declaración de mayoría de edad. Atención manual mediante juls0593@gmail.com. Integraciones opcionales de WhatsApp, Telegram y OpenAI descritas según su función.

## Fuera de alcance

Despliegue, envío a Meta, publicidad, cobros de Fin-si, cancelación automática, verificación documental de edad y campañas por correo.

## Comportamiento esperado

El registro requiere aceptar los documentos presentados y declarar 18 años cumplidos. Las cuentas existentes deben aceptar la versión vigente antes de operar. Permanecen disponibles documentos públicos, autenticación, recuperación, verificación de correo, consulta mínima del perfil y cierre de sesión. Una revisión obsoleta produce 409; el bloqueo de operaciones produce 428 con código legal_acceptance_required. La falta de configuración impide consentir documentos incompletos.

## Decisiones técnicas

Laravel conserva la fuente única de identidad, contenido y huellas SHA-256. Las huellas incluyen identidad y contenido publicado. Las aceptaciones conservan versiones, documentos completos, identidad presentada, declaraciones y fecha del servidor. No se registran IP ni agente de usuario. La repetición de la misma aceptación es idempotente.

## Componentes afectados

### Backend

Configuración legal, acciones de documentos y aceptación, registro transaccional, middleware de operaciones, entrada de Telegram y HTML público.

### Frontend

Reutilizar AppCard, AppTitle, AppText, AppButton, AppLink, RegisterLegalFields y las previsualizaciones actuales. Añadir página de aceptación y tratamiento de errores recuperables, revisiones obsoletas y redirecciones conservando invitaciones.

### Persistencia

Nueva tabla de aceptaciones asociada al usuario, sin consentimiento retroactivo. Migración generada con Artisan. Restricción única por usuario y par de huellas.

### API

- GET /api/legal-documents: identidad pública, disponibilidad y documentos estructurados.
- GET /api/legal-acceptance: estado del usuario autenticado.
- POST /api/legal-acceptance: declaraciones explícitas y huellas vigentes.
- POST /api/auth/register: mismas declaraciones y huellas, usuario y aceptación atómicos.

## Validaciones

Campos obligatorios del titular desde configuración; declaraciones aceptadas; huellas exactas de los documentos actuales; sesión requerida para aceptación posterior.

## Permisos y seguridad

El servidor comprueba la aceptación en operaciones de negocio y Telegram antes de procesar información financiera. Los webhooks y procesos internos conservan sus garantías operativas. Escapar identidad y documentos en HTML; no publicar secretos.

## Casos límite

Configuración incompleta, revisión modificada mientras el formulario está abierto, reintento de aceptación, sesiones anteriores, cuentas sin correo verificado, invitaciones y fallos de red.

## Estrategia de pruebas

Cubrir documentos, huellas, evidencia, registro atómico, estados HTTP, bloqueo de operaciones y Telegram; comprobar registro y rutas públicas/autenticadas en Vue.

## Estrategia de validación

Ejecutar pruebas PHP y unitarias Vue, type-check, build y formato aplicable; comprobar páginas públicas y aceptación en navegador móvil y escritorio. Guardar evidencia local en el recorrido excluido del repositorio.

## Estrategia de commits

Commits convencionales en inglés, separando backend y frontend si permite mantener una unidad revisable y coherente. Ningún secreto ni captura se incorpora al repositorio.

## Archivos estimados a modificar

config/legal.php, app/Services/Legal, controladores y solicitudes API, middleware, modelo y migración, routes/api.php, bootstrap/app.php, resources/views/spa.blade.php, módulo auth de Vue, router, cliente API y pruebas relacionadas.

## Riesgos

La publicación requiere configurar las variables legales en producción y ejecutar la migración durante el despliegue. La implementación local no resuelve por sí sola la verificación de Meta. Los derechos de privacidad requieren atención manual del titular.

## Preguntas abiertas

Sin preguntas bloqueantes; aplicar las decisiones acordadas durante la planeación.

## Plan de implementación

1. Crear fuente canónica y contratos de documentos; verificar configuración, huellas y contenido público.
2. Persistir evidencia y conectar registro, aceptación y controles de acceso; verificar atomicidad, idempotencia y bloqueo.
3. Consumir documentos desde Vue, integrar casillas y página de aceptación, conservar navegación e invitaciones; verificar tipos y pruebas unitarias.
4. Configurar exclusivamente datos legales autorizados en el entorno local, validar suites y navegador, actualizar recorrido y realizar commits locales.

## Resultado de implementación y validación

Se implementaron los contratos, el middleware, el registro transaccional mediante RegisterUser y la evidencia sin aceptación retroactiva. Los documentos públicos comparten la fuente canónica con Vue. La interfaz conserva el destino posterior y mantiene accesibles recuperación, verificación y documentos públicos. Se cubrió además el cambio de revisión durante la validación asíncrona del registro.

Validación local: 352 pruebas PHP aprobadas y una omitida por la suite; 265 pruebas Vue aprobadas en 68 archivos; type-check y build aprobados. ESLint pasó en los archivos modificados. El formato PHP se verificó mediante una configuración temporal que corrige una opción obsoleta del formatter y evita convertir closures de Pest a estáticas; no se modificó la configuración global. Las páginas se comprobaron en escritorio y móvil, sin desbordamiento horizontal, y se completó la aceptación de una cuenta temporal conservando la invitación de destino. La cuenta de prueba fue eliminada.

La migración está aplicada únicamente en la base local. Para producción se deben configurar LEGAL_PROVIDER_NAME, LEGAL_PROVIDER_RFC, LEGAL_PROVIDER_ADDRESS, LEGAL_PROVIDER_EMAIL, LEGAL_PROVIDER_PHONE, LEGAL_PROVIDER_SITE_URL y LEGAL_PROVIDER_BRAND, junto con las versiones LEGAL_TERMS_VERSION y LEGAL_PRIVACY_VERSION; ejecutar la migración y reconstruir frontend/configuración según el procedimiento de despliegue existente. Cambiar contenido o identidad modifica las huellas y exige aceptación nueva. No se desplegó ni se reenviaron solicitudes a Meta.

Las capturas y el recorrido permanecen en docs/integrations/meta-whatsapp, excluidos localmente de Git.
