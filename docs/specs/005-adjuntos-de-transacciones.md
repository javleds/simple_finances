# Adjuntos opcionales de transacciones

## Contexto y objetivo
Permitir comprobantes opcionales en transacciones personales y compartidas con procesamiento asíncrono, almacenamiento privado y uso acotado de recursos.

## Alcance y comportamiento
JPEG, PNG, WebP y HEIC/HEIF se convierten a una única imagen WebP; PDF/XML/ZIP conservan bytes originales. ZIP no se extrae de forma persistente. Estados: pendiente, procesando, disponible y fallido. Todos los miembros actuales consultan y descargan; solo el autor del movimiento agrega, reintenta o elimina. Los adjuntos siguen a su transacción si cambia de cuenta. Borrar una cuenta lógicamente conserva archivos y revoca acceso.

Captura con sección Comprobantes opcional después de Categoría, selección múltiple, cámara móvil y arrastre escritorio. Guardar primero el movimiento y subir después archivos individuales con identificador idempotente. Un fallo no duplica ni revierte el movimiento; conservar pendientes locales y permitir reintentar o continuar explícitamente. Crear otro limpia la selección. Confirmar cierre si hay archivos sin subir. Clip con contador en listas y modal con imágenes ampliables y documentos descargables. Consultar estados cada cinco segundos con pantalla visible.

## Decisiones técnicas y persistencia
Relación propia uno a muchos y Laravel Storage; sin Spatie, antivirus, OCR, visor PDF, cuotas por cuenta ni almacenamiento externo. Registrar metadatos, rutas privadas, tamaños, reserva, estado y token de subida único por transacción. Reservas y límites serializados en base de datos. Contar archivos temporales, finales y capacidad de conversión. Disco dedicado privado. Metadatos de borrados se conservan hasta completar limpieza física. Los archivos no afectan importes, ledger, custodia, reembolsos o notificaciones financieras.

## Configuración
`config/transaction_attachments.php`, variables opcionales `TRANSACTION_ATTACHMENTS_*` en `.env.example`, `env()` solo en configuración. Predeterminados: 5 adjuntos; 5 MiB por imagen/PDF/ZIP; 1 MiB XML; 15 MiB por transacción; 5 GiB global; 1 GiB libre. Imágenes: 40 megapíxeles de entrada, lado máximo 2560 px de salida sin ampliar, WebP calidad 85. ZIP: 20 archivos, 20 MiB expandidos y relación 100:1. Límites de procesamiento, reintentos y temporales configurables. Exponer solo configuración pública al frontend.

## Backend y API
GET `/api/transaction-attachments/config`; GET/POST `/api/transactions/{transaction}/attachments`; GET `.../{attachment}/download`; POST `.../{attachment}/retry`; DELETE `.../{attachment}`. POST multipart individual con token devuelve 202; repetición devuelve adjunto existente. Agregar `attachments_count` sin cargar archivos a listados. Respetar formatos de error actuales.

Jobs en cola `attachments`, conexión database con la misma BD que metadatos. Tres intentos, backoff 10/30 segundos y timeout 60; retry_after superior. Jobs idempotentes y protegidos de concurrencia, muerte del worker y eliminación durante procesamiento. Publicar solo resultados terminados; no servir temporales. Procesar sin autenticación contextual del worker, resolviendo relaciones explícitas.

## Validaciones, permisos y seguridad
Membresía vigente y cuenta activa en cada lectura o descarga; autor y membresía para escrituras. Nombres saneados, rutas generadas, MIME real, extensión y estructura. XML sin DTD ni entidades externas. ZIP sin cifrado, compresiones anidadas, enlaces, rutas peligrosas o archivos fuera de formatos permitidos; lectura expandida acotada. Imágenes con orientación y color normalizados y metadatos eliminados; límites de recursos del decodificador y formatos/delegados restringidos. Descargas privadas JWT, nosniff; blobs autenticados en Vue con URL liberada al cerrar. Validación no equivale a detección de malware.

## Operación y despliegue
Compose incluye attachments-worker (una instancia inicial, memoria 1 GiB, parada gradual hasta 90 s) y attachments-maintenance sin puertos ni Traefik, misma imagen, UID/GID y storage que PHP. Instalar ImageMagick/Imagick/libheif y verificar codecs. Ajustar límites PHP/Nginx a subida individual de 5 MiB más multipart. Desarrollo consume default y attachments.

`deploy.sh`: mantenimiento; detener consumidores de adjuntos antes de git pull; construir imagen compartida, instalar dependencias y publicar assets; iniciar aplicación y migrar/cachear; recrear consumidores; ejecutar diagnóstico encolado de conversión y almacenamiento con timeout 120 s; reabrir solo al pasar. Ante error, mantenimiento y consumidores nuevos detenidos, jobs conservados. No ejecutar acciones remotas.

Mantenimiento específico cada hora, sin duplicar scheduler financiero: completar borrados, limpiar fuentes fallidas y temporales huérfanos >24 h y reconciliar capacidad. Tras expirar fuente pedir nueva selección. Documentar reintentos, capacidad, logs, licencias y respaldo conjunto archivos/BD.

## Casos límite y riesgos
Subidas concurrentes, disco lleno, duplicación por respuesta perdida, archivos malformados, bombas ZIP/XML/imágenes, worker detenido, archivo borrado durante procesamiento, transacción eliminada por cascada y cuenta compartida con miembros retirados. Licencias abiertas de herramientas no constituyen garantía universal sobre patentes de códecs. No conservar originales fotográficos. Conversión con compresión conserva legibilidad, no identidad binaria.

## Componentes y archivos afectados
Modelos/migraciones/acciones/jobs/controlador y rutas; cliente HTTP multipart/binario; tipos/repositorios/composables/componentes transacciones; formularios personales y compartidos; Compose, imagen PHP, deploy.sh y documentación operativa.

## Estrategia de pruebas y validación
Pruebas por acción y API: aislamiento, permisos, formatos reales, seguridad, límites concurrentes, capacidad, idempotencia, estados/reintentos y borrado. Vue: guardado parcial, cierre, crear otro, polling visible, descarga. Playwright móvil/escritorio. Laravel/Pest, Vitest, type-check, build, ESLint enfocado, bash -n, Compose y tests de despliegue simulados. Diagnóstico local de codecs y job real cuando estén disponibles.

## Plan de implementación y commits
1. Persistencia, configuración, almacenamiento y reservas: feat(attachments): add private attachment storage and capacity limits.
2. Validación, conversión, jobs, API y limpieza: feat(attachments): process and serve transaction receipts asynchronously.
3. Captura y consulta UI: feat(ui): attach and browse transaction receipts.
4. Servicios, despliegue y documentación: feat(ops): deploy attachment workers and maintenance.

## Preguntas abiertas
Ninguna. Sin cuotas por cuenta ni ClamAV en este MVP.
