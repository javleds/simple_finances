# Vinculación de cuenta con WhatsApp

## Alcance actual

En `/admin/settings`, la sección WhatsApp permite vincular y desvincular el teléfono del usuario autenticado. Utiliza PrimeVue 5 con Tailwind: código de país predeterminado `+52`, teléfono de diez dígitos y entrada de seis caracteres para el código de verificación.

El acceso a la SPA continúa usando correo, contraseña y JWT. La verificación por WhatsApp vincula un teléfono; todavía no constituye un login ni emite un JWT. La prueba completa de envío y confirmación con Meta está pendiente. Esta documentación no cambia el comportamiento del OTP.

## Configuración

Las variables están declaradas en `.env.example`:

| Variable | Contenido |
| --- | --- |
| `WA_PHONE_NUMBER_ID` | Identificador del número emisor asignado por Meta; no es el número del usuario ni el identificador de la cuenta Business. |
| `WA_ACCESS_TOKEN` | Token de acceso de Meta utilizado para enviar mensajes. |
| `WA_API_TOKEN` | Cadena elegida por el operador que debe coincidir con el token de verificación configurado en el webhook de Meta. |
| `WA_APP_SECRET` | Secreto de la aplicación Meta utilizado para verificar la firma de los POST entrantes. |
| `WA_AUTH_TEMPLATE_NAME` | Nombre exacto de la plantilla de autenticación aprobada que enviará el código. |
| `WA_AUTH_TEMPLATE_LANGUAGE` | Código exacto del idioma aprobado para esa plantilla; ejemplo: `es_MX`. |
| `VITE_PRIMEVUE_LICENSE_KEY` | Licencia del frontend compatible con Vite; se expone en el build del navegador. |
| `OPENAI_API_TOKEN` | Credencial existente de OpenAI, conservada para los flujos que ya la utilizan. |

Las credenciales `WA_*` pertenecen al backend y no deben llevar prefijo `VITE_`. El flujo actual de recepción de WhatsApp no llama a OpenAI.

La sección de vinculación se oculta cuando falta alguno de los seis valores `WA_*` de configuración de envío o webhook de la tabla anterior. `WA_AUTH_TEMPLATE_LANGUAGE` utiliza `es_MX` como valor predeterminado si no se define; un valor vacío deshabilita la sección. Laravel publica únicamente el indicador booleano `app-whatsapp-enabled` en el HTML, sin credenciales. Cuando está deshabilitada, la tarjeta no se monta ni consulta el estado de vinculación. Las conexiones existentes se conservan. Después de cambiar la configuración, actualizar la caché de configuración si se utiliza y recargar la página; no se requiere recompilar el frontend.

La plantilla se administra en WhatsApp Manager, en las plantillas de mensajes de la cuenta Business. Debe ser de autenticación con código y botón para copiarlo: el envío implementado incluye el mismo código en el cuerpo y en el botón. La cuenta de prueba utilizada rechazó la creación de plantillas personalizadas; la validación real sigue pendiente hasta disponer de una plantilla autorizada.

`hello_world` no permite insertar el OTP como parámetro: cambiar solamente `WA_AUTH_TEMPLATE_NAME` a ese valor no prueba este flujo. No se ha implementado una alternativa de envío de texto para desarrollo.

## Verificación del teléfono

1. El usuario solicita un código para un teléfono con código de país.
2. El backend genera seis dígitos, conserva su hash con expiración de 24 horas y envía la plantilla mediante la API de Meta.
3. Si Meta acepta el envío, el código queda disponible para confirmación. La aceptación no garantiza entrega al dispositivo.
4. El usuario introduce el código; la confirmación correcta consume el código y crea la vinculación.
5. Desvincular elimina la conexión e invalida los códigos pendientes. Una nueva vinculación requiere verificación.

Un teléfono verificado solo puede pertenecer a un usuario y cada usuario puede tener una conexión. El teléfono general del perfil es independiente de esta vinculación. Un reenvío invalida el código previo; un fallo de envío no deja un código confirmable. Se permiten cinco intentos de confirmación, una solicitud por minuto y cinco por hora, con controles por usuario y teléfono. Los ceros iniciales forman parte del código.

Los endpoints de vinculación requieren JWT:

| Método y ruta | Entrada | Resultado |
| --- | --- | --- |
| `GET /api/whatsapp-connection` | Sin cuerpo | Estado `unlinked`, `pending` o `linked`, teléfono, expiración y disponibilidad de reenvío. |
| `POST /api/whatsapp-verification-codes` | `{"phone_number":"+525512345678"}` | `201` cuando Meta acepta el envío. |
| `POST /api/whatsapp-connection` | `{"code":"001234"}` | `200` al confirmar la vinculación. |
| `DELETE /api/whatsapp-connection` | Sin cuerpo | `200`; la desvinculación es idempotente. |

Los errores distinguen validación o código inválido (`422`), conflicto de vinculación (`409`), límite de solicitudes (`429`) y problemas del proveedor o de configuración (`502`/`503`). El código no se devuelve en la respuesta de la API.

## Webhook y procesamiento asíncrono

Meta utiliza la misma URL pública HTTPS para ambos métodos: `/api/whatsapp/webhook`.

- El GET recibe `hub.mode`, `hub.verify_token` y `hub.challenge`; con modo y token correctos devuelve el desafío como texto plano, con su longitud explícita. No utiliza el token de acceso de envío.
- El POST valida `X-Hub-Signature-256` mediante `WA_APP_SECRET`, el formato del evento y el identificador del número receptor antes de persistirlo.

El POST guarda el evento cifrado en `webhook_receipts` y encola un job en `jobs` dentro de la misma transacción. Solo responde `200` después del commit. Una firma inválida devuelve `403`, un evento inválido `422` y una configuración o persistencia fallida `503`.

Se requiere `QUEUE_CONNECTION=database`, con recibos y jobs en la misma conexión. `sync` o Redis no satisfacen el contrato implementado. La clave única combina proveedor y hash del cuerpo original: repetir exactamente el mismo cuerpo no crea otro recibo ni otro job. Esto no implica deduplicación por identificador de mensaje entre cuerpos diferentes.

El job recibe el identificador del recibo, admite cinco intentos y tiene timeout de 60 segundos. `retry_after` debe ser mayor; el valor predeterminado de la cola es 90 segundos. Un recibo ya procesado se omite. Los fallos agotados quedan disponibles en `failed_jobs` y el recibo pasa a estado fallido.

Actualmente el procesador valida el proveedor y la estructura principal y marca el recibo como procesado. Todavía no interpreta mensajes, responde al usuario ni crea transacciones financieras. Un `200` del webhook confirma recepción durable, no finalización del procesamiento. Telegram conserva su flujo anterior.

La persistencia utiliza `whatsapp_connections`, `whatsapp_verification_codes`, `whatsapp_verification_locks`, `webhook_receipts` y las tablas de colas de Laravel. Los códigos se guardan como hash y los cuerpos de webhook cifrados.

## Prueba pendiente con Meta

1. Aplicar migraciones y completar las variables con una plantilla de autenticación aprobada.
2. Ejecutar `composer run dev`, que inicia PHP, Vite y el worker de colas.
3. Exponer el puerto PHP `8000` mediante un túnel HTTPS público, sin autenticación del túnel; no utilizar el puerto de Vite.
4. Registrar la URL del webhook y el token `WA_API_TOKEN` en Meta, completar la verificación GET y suscribir `messages`.
5. Desde una sesión de la SPA, solicitar el código y comprobar entrega, confirmación, estado vinculado y desvinculación.
6. Enviar un evento de prueba desde Meta y comprobar recibo, job y estado procesado.

La publicación de la aplicación Meta y el acceso público al backend son requisitos distintos. El aviso de Meta para aplicaciones sin publicar limita los eventos que entrega; no explica por sí solo un fallo de verificación GET. Si la verificación falla, comprobar acceso anónimo al túnel, token exacto y respuesta del desafío sin HTML de depuración.

Consulta [desarrollo local](../operations/local-development.md), [operación en despliegue](../operations/deployment.md) y [pruebas](../testing/strategy.md).
