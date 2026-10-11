# Comprobantes de transacciones

Los movimientos admiten varios comprobantes opcionales: fotos de tickets, facturas PDF, XML y ZIP. No modifican importes, saldos, custodia, asignaciones ni reembolsos. Se implementan con una relación propia y Laravel Storage; no se utiliza Spatie Media Library.

## Registro y procesamiento

La sección **Comprobantes · Opcional** permite elegir archivos y, en móvil, tomar una foto. En escritorio también admite arrastrar archivos. Primero se guarda la transacción y después se suben los archivos individualmente. Si una subida falla, el movimiento permanece guardado: el usuario puede reintentar o continuar sin los archivos pendientes, sin repetir la operación financiera ni sus notificaciones.

Cada subida lleva un `upload_id` UUID reutilizado al reintentar esa misma selección. La API responde `202` al aceptar el archivo; eso no garantiza que su contenido supere el procesamiento posterior.

| Estado API | Presentación | Comportamiento |
| --- | --- | --- |
| `pending` | En espera | Recibido y pendiente de procesar |
| `processing` | Procesando | Validación y conversión en curso |
| `ready` | Disponible | Se permite descargar; las imágenes tienen miniatura y visor |
| `failed` | No se pudo procesar | Muestra el error; permite reintentar si conserva la fuente |

La sección visible consulta estados pendientes cada cinco segundos. Un fallo de red al cargar una miniatura tiene su propio reintento y no cambia el estado de procesamiento del archivo. Tras expirar la fuente de un archivo fallido, hay que volver a seleccionarlo; su registro permanece hasta eliminarlo y sigue contando para el límite de archivos.

## Consulta en móvil y escritorio

El clip de cada movimiento muestra el número de comprobantes y abre su consulta. El formulario también permite revisar los existentes al editar. La galería tiene dos columnas en móvil y tres desde `sm`: imágenes con miniatura y documentos con icono, nombre, tamaño y estado.

JPEG, PNG y WebP seleccionados localmente tienen miniatura antes de guardar. HEIC/HEIF muestran una tarjeta hasta que exista el WebP procesado. PDF, XML y ZIP se descargan; no tienen visor embebido.

El visor usa Galleria de PrimeVue dentro de AppModal: pantalla completa móvil, presentación amplia en escritorio, contador, descarga y navegación sin volver al principio al llegar al final. Permite zoom del 100 % al 400 %, arrastre de la imagen ampliada y pinza. Sin zoom, un deslizamiento horizontal cambia de imagen; las flechas del teclado también navegan. Cambiar de imagen restablece el zoom. Escape cierra solo el visor y devuelve el foco a la miniatura.

Miniatura, visor y descarga reutilizan el mismo WebP autenticado durante la sesión. No se generan archivos de miniaturas adicionales. Las URL temporales se liberan al cerrar la sección, ocultar la pestaña, cambiar de transacción, eliminar un archivo o desmontar el componente. Las respuestas tardías se descartan; si la consulta informa pérdida de acceso, se retiran los archivos almacenados en memoria y se cierra el visor.

## Formatos y límites

Valores predeterminados de `config/transaction_attachments.php`; los tamaños se configuran en bytes mediante las variables opcionales `TRANSACTION_ATTACHMENTS_*` de `.env.example`.

| Límite | Valor predeterminado |
| --- | --- |
| Archivos por transacción | 5 |
| Cada imagen, PDF o ZIP | 5 MiB |
| Cada XML | 1 MiB |
| Total por transacción | 15 MiB |
| Capacidad global de adjuntos | 5 GiB |
| Reserva mínima de disco libre | 1 GiB |
| Imagen de entrada | 40 millones de píxeles |
| Lado máximo del WebP | 2560 px |
| Calidad WebP | 85 |
| Entradas por ZIP | 20 |
| Contenido expandido de ZIP | 20 MiB; relación máxima 100:1 |
| Retención de fuentes fallidas y temporales huérfanos | 24 horas |

Se aceptan JPG/JPEG, PNG, WebP, HEIC/HEIF, PDF, XML y ZIP. Las imágenes se orientan y convierten a un único WebP; el original se elimina después de publicar correctamente el resultado. PDF, XML y ZIP conservan sus bytes. Los ZIP se inspeccionan con límites y no se extraen permanentemente; se rechazan rutas inseguras, enlaces simbólicos, cifrado, archivos anidados no permitidos y contenidos inválidos. XML rechaza DTD y entidades; no resuelve recursos externos. No se ejecuta análisis antivirus.

La capacidad global reserva espacio para fuentes, resultados y procesamiento temporal; no equivale únicamente a sumar tamaños visibles. No existen cuotas por cuenta. Aumentar límites de Laravel exige revisar también los techos PHP, Nginx, ImageMagick y contenedores; véase [operación de adjuntos](../operations/deployment.md#operación-de-adjuntos).

## Permisos y eliminación

Todos los miembros vigentes de la cuenta pueden listar y descargar comprobantes. Solo el autor de la transacción, que además debe conservar acceso a la cuenta, puede subir, reintentar o eliminar. Ser propietario de la cuenta no concede administración sobre comprobantes de movimientos ajenos.

Cada petición valida la membresía actual, la cuenta activa y que el adjunto pertenezca a la transacción indicada. El almacenamiento es privado y no se publica con `storage:link`; las descargas usan JWT, `private, no-store` y `nosniff`. La API no expone rutas físicas ni enlaces públicos.

Eliminar un comprobante revoca su acceso inmediatamente; el mantenimiento retira los archivos físicos después. Al eliminar una transacción, el mantenimiento también retira sus adjuntos huérfanos. Si el movimiento cambia de cuenta, el acceso sigue a la cuenta actual.

## Contrato API

Todas las rutas requieren autenticación. El contrato estructurado está en [OpenAPI](../api.json).

| Operación | Ruta | Respuesta correcta |
| --- | --- | --- |
| Límites para el formulario | `GET /api/transaction-attachments/config` | `200`, `data` con límites públicos y extensiones |
| Listar | `GET /api/transactions/{transaction}/attachments` | `200`, `data` y `meta` con permisos, cantidad y bytes |
| Subir | `POST /api/transactions/{transaction}/attachments` | `202`, multipart con `file` y `upload_id` |
| Descargar | `GET /api/transactions/{transaction}/attachments/{attachment}/download` | `200`, archivo binario |
| Reintentar procesamiento | `POST /api/transactions/{transaction}/attachments/{attachment}/retry` | `202`, requiere fallo y fuente conservada |
| Eliminar | `DELETE /api/transactions/{transaction}/attachments/{attachment}` | `200`, mensaje de confirmación |

Los metadatos incluyen `id`, `original_name`, `mime_type` nullable hasta procesar, `size_bytes`, `status`, `error_message`, `can_manage`, `can_retry` y `created_at`. Las transacciones incluyen `attachments_count`; no incluyen los archivos binarios. La descarga devuelve el nombre con extensión `.webp` para imágenes convertidas.

Los errores relevantes son `403` por permisos insuficientes, `404` por recurso no disponible o relación incorrecta, `409` por archivo aún no disponible o reintento imposible, `422` por límites/formato de subida y `507` por capacidad insuficiente. La validación de contenido asíncrona puede terminar en `failed` después de una aceptación `202`.

## Referencias operativas y pruebas

- [Desarrollo local e Imagick](../operations/local-development.md#adjuntos-de-transacciones).
- [Worker, mantenimiento, diagnóstico y respaldo](../operations/deployment.md#operación-de-adjuntos).
- [Cobertura y verificación de navegador](../testing/strategy.md#adjuntos-de-transacciones).
- Especificaciones [de adjuntos](../specs/005-adjuntos-de-transacciones.md) y [de galería](../specs/006-galeria-de-comprobantes.md).
