# Ocultar WhatsApp sin configuración

## Contexto

Configuración monta WhatsappConnectionCard aunque el backend no tenga credenciales de WhatsApp.

## Objetivo

Ocultar la sección completa cuando la integración no esté configurada, sin consultas de vinculación innecesarias.

## Alcance

Indicador de disponibilidad en el HTML de la SPA y montaje condicional de la tarjeta existente.

## Fuera de alcance

Activar credenciales, enviar mensajes, modificar conexiones guardadas o reglas del webhook.

## Comportamiento esperado

La sección aparece únicamente cuando los seis valores de configuración necesarios son cadenas no vacías: phone_number_id, access_token, verify_token, app_secret, auth_template_name y auth_template_language. El idioma conserva el valor predeterminado es_MX. Si el indicador no existe o no es true, se oculta. Notificaciones y utilidades permanecen disponibles.

## Decisiones técnicas

IsWhatsAppConfigured consulta config(), compatible con config:cache. Blade publica solo true/false en un meta; ConfigPage usa v-if antes de montar WhatsappConnectionCard. Se reutilizan la tarjeta y su flujo actual sin añadir variables VITE ni endpoints.

## Componentes afectados

Vista spa, ConfigPage y acción de lectura de configuración.

## Backend

Nueva acción tipada con un método execute(): bool; no usa env() fuera de configuración.

## Frontend

Leer el meta al crear ConfigPage y montar la tarjeta solo si está habilitada.

## Persistencia

Sin cambios; no borrar conexiones existentes.

## API

Sin cambios de contratos, rutas o autorizaciones.

## Validaciones

Valores ausentes, vacíos, con espacios o no textuales deshabilitan la sección.

## Permisos y seguridad

Solo el booleano sale del servidor. No publicar tokens, secretos, identificadores ni plantillas. Ocultar la sección no sustituye validaciones de API.

## Casos límite

Configuración parcial, idioma por defecto, indicador ausente o inválido y usuario ya vinculado con integración deshabilitada.

## Estrategia de pruebas

Acción: todas las claves completas y cada clave inválida. HTML: indicador correcto y ausencia de secretos. Vue: montaje habilitado y ausencia de tarjeta/consulta cuando está deshabilitada, conservando otras secciones.

## Estrategia de validación

Pruebas PHP enfocadas en disponibilidad y WhatsApp; pruebas Vue, type-check, build, ESLint y diff --check.

## Estrategia de commits

fix(settings): hide WhatsApp linking when integration is unconfigured

## Archivos estimados a modificar

app/Services/WhatsApp/IsWhatsAppConfigured.php, resources/views/spa.blade.php, ConfigPage.vue, pruebas relacionadas y docs/integrations/whatsapp.md.

## Riesgos

El indicador se calcula al servir HTML; tras cambiar configuración debe actualizarse la caché de Laravel si se utiliza y recargarse la página. Tener credenciales no acredita aprobación de plantilla ni entrega real.

## Preguntas abiertas

Ninguna.

## Plan de implementación

1. Crear y probar la acción con las claves usadas por envío y webhook.
2. Publicar el booleano en Blade y comprobar que no salen secretos.
3. Condicionar el montaje de la tarjeta en ConfigPage y probar que no consulta cuando está oculta.
4. Actualizar la guía de configuración, ejecutar verificaciones y crear commit local.
