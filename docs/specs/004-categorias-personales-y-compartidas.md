# Categorías personales y compartidas

## Contexto y objetivo
Agregar clasificación opcional a movimientos existentes y nuevos, conservando las reglas financieras y la separación entre catálogos personales y compartidos.

## Alcance y comportamiento
El campo Categoría aparece a la derecha de Cantidad en escritorio y debajo en móvil y permite buscar, crear y seleccionar sin salir del formulario. Más incluye administración de catálogos, nombres y eliminación con decisión explícita entre dejar movimientos sin categoría o reasignarlos dentro del mismo catálogo. La interfaz identifica las categorías compartidas y su alcance sobre otros miembros.

## Decisiones técnicas y persistencia
Las categorías pertenecen a un usuario o a una cuenta, nunca ambos. Los nombres normalizados son únicos por catálogo. Las transacciones tienen categoría nullable; los movimientos actuales permanecen sin categoría. Las cuentas conservan un indicador de catálogo compartido aunque vuelva a quedar un miembro. Al compartir por primera vez se copian las categorías usadas y se reasignan únicamente los movimientos de la cuenta.

## Backend y API
Usar acciones explícitas para crear, actualizar, eliminar y convertir catálogos. GET/POST /api/categories y /api/accounts/{account}/categories; PUT/DELETE con identificador. El endpoint de cuenta resuelve su catálogo efectivo. DELETE acepta action uncategorize o reassign y target_category_id cuando corresponde. Los contratos de transacciones incluyen category_id y categoría relacionada; omitir el campo en una actualización conserva la selección.

## Frontend y componentes afectados
Extender AppSearchSelect con creación opcional. Integrar el selector en TransactionsForm y mostrar la categoría debajo del concepto en listados. Crear módulo categories con schemas, repositorio, consultas y página. Reutilizar AppModal, AppInput, AppButton, AppActionMenu y estados de listas.

## Validaciones, permisos y seguridad
Nombre requerido hasta 100 caracteres, normalizado para evitar duplicados por mayúsculas y espacios. Todos los miembros crean y usan categorías compartidas; solo el propietario administra nombres y elimina. La eliminación puede reclasificar movimientos de otros miembros y debe explicitarlo. Validar catálogo, membresía y destino en backend; reclasificar y eliminar atómicamente sin modificar saldos, ledger, asignaciones o reembolsos.

## Casos límite y riesgos
Controlar creación duplicada concurrente, uso aparecido después de abrir confirmación, categorías borradas durante captura, invitaciones rechazadas, incorporación directa de miembros y repetición de conversiones. La categoría creada permanece si se cancela el movimiento. Sin categorías precargadas, jerarquías, colores, reportes nuevos ni clasificación por mensajería.

## Estrategia de pruebas y validación
Pruebas backend por acción y API para aislamiento, permisos, selección, eliminación, reasignación, compatibilidad y transición compartida. Pruebas Vue para selector y contratos. Ejecutar php artisan test, npm run test:unit -- --run, npm run type-check y npm run build. Revisar móvil, escritorio y teclado.

## Plan de implementación y commits
1. Persistencia, acciones, permisos y pruebas backend: feat(categories): add personal and shared category catalogs.
2. Contratos, selector, administración y pruebas frontend: feat(ui): manage and select transaction categories.
3. Validar integración y corregir regresiones antes de completar los commits.

## Archivos estimados y fuera de alcance
Backend en app, routes/api.php y migraciones generadas con Artisan. Frontend en módulos categories, transactions, accounts y componentes compartidos. No modificar reglas de importes ni dependencias.

## Preguntas abiertas
Ninguna. Permisos de administración y conservación del catálogo confirmados por el usuario.
