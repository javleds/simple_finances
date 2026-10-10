# Unificar el diseño móvil

## Contexto

La auditoría de 24 vistas y 106 capturas detectó padding acumulado, superficies anidadas, acciones con colores inconsistentes, navegación inferior duplicada y desbordamiento del selector de cuentas. Las capturas permanecen fuera del repositorio.

## Objetivo

Aplicar el sistema visual aprobado, petróleo y neutros en claro y oscuro, conservando todos los datos, campos, ayudas, botones, estados y permisos.

## Alcance

Componentes App*, navegación móvil, dashboard, cuentas y libro, transacciones, ahorro, suscripciones, distribución, configuración, perfil y autenticación. Anchos de referencia: 360, 390 y 430 px.

## Fuera de alcance

Rediseño desktop, cambios de dominio, dependencias, endpoints, persistencia y reglas de cuentas compartidas.

## Comportamiento esperado

Margen de página y tarjetas de 16 px; campos separados 16 px; secciones 24 px. Encabezados sin tarjeta. Importes completos, nombres ajustables y overlays contenidos. Cancelar/cerrar neutrales, eliminar rojo, guardar/crear primarios. Formularios largos y documentos legales fullscreen; formularios cortos y filtros en sheet; confirmaciones compactas.

## Decisiones técnicas

Reutilizar AppCard, AppSectionHeader, AppSectionBar, AppModal, AppButton, AppSearchSelect y adaptadores existentes. Extender presentation con fullscreen y AppButton con danger. Mantener selector .dark, capas CSS y preset Aura. Conservar reglas sm de distribución desktop.

## Componentes afectados

Tokens, preset, shell, pestañas de cuenta, primitives compartidos, composiciones de vistas y productores de acciones.

## Backend

Sin cambios. Preservar custodia, liquidaciones, ledger, balances y autorizaciones.

## Frontend

Escala 4/8/12/16/24/32 px; controles de 48 px y objetivos de iconos de 44 px. Tipografía 24/30 para página, 18/24 para sección, 16/24 para cuerpo y campos. Una superficie por grupo funcional. Mantener navegación global y mover las cinco pestañas de cuenta bajo su encabezado.

## Persistencia

Sin cambios; no modificar datos reales durante validación visual.

## API

Sin cambios de contratos; importes siguen como cadenas, fechas yyyy-MM-dd e identificadores de selectores intactos.

## Validaciones

Conservar errores de campo/general, estado de carga, deshabilitado, validación cruzada y protección contra envíos repetidos.

## Permisos y seguridad

Mantener permisos de edición/eliminación por autor, reglas de reembolsos y restricciones de fondos. No guardar credenciales ni capturas en Git.

## Casos límite

Nombres/descripciones e importes largos; teclado móvil; safe-area; estados vacíos/error/paginación; invitaciones pendientes; WhatsApp no vinculado/OTP/vinculado; correcciones/reversiones del libro. Los estados no disponibles en datos reales se validan mediante fixtures locales/pruebas.

## Estrategia de pruebas

Pruebas de comportamiento de acciones y overlays; conservar suite de formularios, filtros, permisos y finanzas compartidas. Comprobación de viewport y controles con navegador local sin mutaciones financieras.

## Estrategia de validación

Ejecutar type-check, unitarios, build y php artisan test. Revisar claro/oscuro en 360/390/430 px y alturas 640/844. Comprobar que cada elemento existente sigue en la plantilla o tiene ubicación equivalente. Revisar ausencia de regresiones desktop.

## Estrategia de commits

1. feat(ui): unify mobile design system and navigation
2. refactor(ui): align mobile views and form presentations
3. test(ui): verify mobile overlays and preserved workflows

## Archivos estimados a modificar

resources/js/main.css, lib/primevue.ts, módulos shared, admin, accounts, transactions, virtual-accounts, subscriptions, distribution, settings y auth; pruebas relacionadas.

## Riesgos

Clases locales con padding/radios importantes pueden anular primitives. Overlays teletransportados necesitan límites propios. Cambios compartidos requieren revisar desktop. No confundir superposición de barras en capturas completas con problemas reales de scroll.

## Preguntas abiertas

Ninguna decisión de producto pendiente. La cobertura de estados condicionales se completa con datos de prueba, sin enviar códigos o pagos reales.

## Plan de implementación

1. Centralizar tokens y primitives, conservando contratos y comportamiento; validar tipos y controles compartidos.
2. Aplanar shell y mover pestañas; comprobar navegación, safe-area y scroll.
3. Migrar dashboard/resúmenes y módulos, conservando cada dato y acción; revisar diferencias de plantillas.
4. Migrar formularios y tonos de acciones; comprobar confirmaciones, foco y validación.
5. Ejecutar suites y revisión móvil con Chrome DevTools; corregir hallazgos, registrar resultados y crear commits convencionales locales.

## Resultado de implementación

Sistema aplicado a los módulos del alcance, con tokens compartidos claro/oscuro, encabezados semánticos, superficies compactas y presentaciones de modal por complejidad. El selector de cuentas limita su overlay al viewport y permite leer descripciones completas.

| Área | Conservación funcional |
| --- | --- |
| Cuentas y transacciones | Campos, filtros, acciones por autor, reparto, reembolsos y cinco pestañas de cuenta |
| Libro | Timeline, integridad, correcciones, reversiones y confirmaciones |
| Dashboard y ahorro | Métricas, periodos, gráficas, objetivos y captura de saldo |
| Suscripciones y distribución | Formularios, proyecciones, relaciones, filtros y porcentajes |
| Configuración y perfil | Preferencias, notificaciones, WhatsApp, seguridad y calculadora |
| Autenticación | Registro, acceso, recuperación, verificación y textos legales íntegros |

Revisión de bindings de las plantillas y revisión independiente sin pérdidas funcionales detectadas. La cancelación de WhatsApp se trasladó al contrato de cierre de AppModal y cuenta con prueba de comportamiento.

Validación: type-check, build y ESLint correctos; 38 archivos y 111 pruebas frontend; 197 pruebas backend y 1198 aserciones. El script pw:mobile-design verifica 36 combinaciones móviles (360/390/430 px, alturas 640/844, claro/oscuro, tres flujos) y una comprobación desktop. Usa fixtures y bloquea escrituras API.

Oxlint mantiene 12 errores anteriores: comparación contra HEAD con cero errores nuevos. No se modificaron archivos ajenos para corregir esa deuda. Los estados financieros condicionales se respaldan con las pruebas existentes; no se ejecutaron pagos, liquidaciones, correcciones ni envíos de códigos sobre datos reales. La comprobación de teclado se limita a emulación y configuración del viewport, sin prueba en dispositivo físico.

Referencias de implementación PrimeVue: [styled theming](https://primevue.dev/theming/styled), [Dialog](https://primevue.dev/dialog), [Select](https://primevue.dev/select) y [Button](https://primevue.dev/button). Metadatos y usos de los tres componentes validados mediante MCP.
