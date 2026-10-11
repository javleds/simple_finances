# Galería de comprobantes y visor de imágenes

## Contexto y objetivo

Los comprobantes se presentan como texto y la ampliación abre otra pestaña. Se mostrarán miniaturas y un visor navegable sin abandonar la transacción.

## Alcance y comportamiento esperado

Cuadrícula de dos columnas en móvil y tres en escritorio. Imágenes disponibles con miniatura y documentos con icono, nombre y tamaño. Mantener estados, errores, descarga y gestión autorizada. JPEG, PNG y WebP locales tendrán miniatura antes de subir; HEIC/HEIF mostrarán tarjeta hasta procesarse.

Visor con pantalla completa móvil y amplio en escritorio, contador, descarga y navegación sin circulación. Zoom entre 100 % y 400 %, controles, arrastre y pinza; deslizamiento horizontal para navegar solo al 100 %. Flechas del teclado para navegar y Escape para cerrar únicamente el visor. Restablecer zoom al cambiar imagen y devolver el foco al cerrar.

## Decisiones técnicas y componentes afectados

Reutilizar TransactionAttachments, AppModal, AppButton y el repositorio autenticado. Extraer TransactionImageViewer con Galleria de PrimeVue dentro de AppModal para navegación, y controles de zoom y gestos complementarios. Mantener blobs y URL temporales solo durante la sesión visible; reutilizarlos para miniatura, visor y descarga. Descartar respuestas tardías de sesiones anteriores y revocar URL al cerrar, cambiar transacción, eliminar o desmontar. Si desaparece la imagen activa, cerrar el visor.

## Backend, persistencia y API

Sin cambios. Reutilizar los WebP existentes y el endpoint privado de descarga. Sin nuevas dependencias ni archivos de miniaturas.

## Validaciones, permisos y seguridad

Conservar validación de archivos y límites. Consultan miembros actuales y administran autores según API. No usar enlaces públicos. Errores y reintentos por miniatura sin bloquear otros archivos. Controles táctiles de al menos 44 px, nombres largos sin desbordamiento y foco contenido en modal.

## Fuera de alcance

Vista previa de PDF/XML/ZIP, OCR y almacenamiento de miniaturas. El listado de transacciones mantiene el acceso compacto mediante el clip.

## Casos límite y riesgos

Descarga pendiente al cerrar, eliminación durante vista previa, cambio de transacción, formatos locales no soportados y fallos individuales. Hasta cinco WebP completos en memoria; liberar recursos al terminar la sesión.

## Estrategia de pruebas y validación

Pruebas unitarias de miniaturas locales/remotas, recursos y respuestas tardías, fallos y reintentos, estados y permisos; visor con navegación, zoom y gestos. Playwright móvil/escritorio para cuentas personales/compartidas, descarga autenticada y conservación del formulario. Ejecutar TypeScript, suite unitaria, ESLint de archivos modificados y build.

## Plan de implementación y commits

1. Implementar recursos de galería y tarjetas, verificar estados y permisos.
2. Integrar visor, navegación y gestos con pruebas dirigidas.
3. Validar navegador, tipos, suite y build; revisar cambios.
4. Commit convencional en inglés de la mejora de consulta y sus regresiones.

## Archivos estimados a modificar

TransactionAttachments.vue y sus pruebas; nuevos TransactionImageViewer.vue y pruebas; verificador Playwright de adjuntos.

## Preguntas abiertas

Ninguna. Orden del listado para imágenes disponibles; miniaturas sin almacenamiento adicional.
