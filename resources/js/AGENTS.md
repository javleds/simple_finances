# AGENTS.md

## Shared Components Index

Before creating new UI for features, review `resources/js/modules/shared/components` and reuse these components when they fit the need.

Reusing an existing shared component is mandatory when it already covers the need or can cover it with a small, reasonable extension. Do not create native UI elements or new components from scratch if a shared component already applies.

Current shared components:

- `AppActionMenu`: compact contextual actions menu for secondary item actions such as edit and delete.
  Path: `resources/js/modules/shared/components/AppActionMenu.vue`
- `AppAvatarValueRow`: compact row with circular initials avatar, primary label and right-aligned value for small summaries.
  Path: `resources/js/modules/shared/components/AppAvatarValueRow.vue`
- `AppButton`: base button with variants `primary`, `secondary`, `ghost`.
  Path: `resources/js/modules/shared/components/AppButton.vue`
- `AppCard`: bordered surface card with optional padding and muted surface mode.
  Path: `resources/js/modules/shared/components/AppCard.vue`
- `AppContextTabs`: contextual horizontal tabs with optional icons and configurable top or bottom active indicator.
  Path: `resources/js/modules/shared/components/AppContextTabs.vue`
- `AppDatePicker`: adaptador de PrimeVue DatePicker que conserva fechas locales como cadenas `yyyy-MM-dd` y valores vacíos como `null`.
  Path: `resources/js/modules/shared/components/AppDatePicker.vue`
- `AppEmptyState`: dashed empty-state surface for list sections with optional action slot.
  Path: `resources/js/modules/shared/components/AppEmptyState.vue`
- `AppHeroMetric`: hero-style metric block with small label, large value and optional adornment slot.
  Path: `resources/js/modules/shared/components/AppHeroMetric.vue`
- `AppIconButton`: compact circular button for icon-only actions.
  Path: `resources/js/modules/shared/components/AppIconButton.vue`
- `AppInput`: labeled input wrapper compatible with native input attributes via `$attrs`.
  Path: `resources/js/modules/shared/components/AppInput.vue`
- `AppLink`: shared link component compatible with `href` and Vue Router `to`, with variants `primary`, `secondary`, `subtle`.
  Path: `resources/js/modules/shared/components/AppLink.vue`
- `AppListState`: wrapper for initial loading and initial error states before rendering list content.
  Path: `resources/js/modules/shared/components/AppListState.vue`
- `AppLoadMoreFooter`: dashed infinite-scroll footer with status label and optional retry action.
  Path: `resources/js/modules/shared/components/AppLoadMoreFooter.vue`
- `AppModal`: base modal mobile-first with header, content area and footer actions.
  Path: `resources/js/modules/shared/components/AppModal.vue`
- `AppPasswordInput`: password input with show/hide action.
  Path: `resources/js/modules/shared/components/AppPasswordInput.vue`
- `AppPercentageSplitEditor`: interactive horizontal percentage splitter with drag handles and exact numeric adjustment that keeps the total at 100%.
  Path: `resources/js/modules/shared/components/AppPercentageSplitEditor.vue`
- `AppSearchSelect`: adaptador de PrimeVue Select con búsqueda, limpieza, descripciones de opciones y etiquetas accesibles.
  Path: `resources/js/modules/shared/components/AppSearchSelect.vue`
- `AppSectionBar`: compact section heading row with title, actions slot and bottom divider.
  Path: `resources/js/modules/shared/components/AppSectionBar.vue`
- `AppSectionHeader`: reusable section header card with title, optional description, compact metrics area and actions slot.
  Path: `resources/js/modules/shared/components/AppSectionHeader.vue`
- `AppSwitch`: boolean on/off switch for compact settings and per-item activation controls.
  Path: `resources/js/modules/shared/components/AppSwitch.vue`
- `AppText`: shared paragraph/text primitive with tone and size options.
  Path: `resources/js/modules/shared/components/AppText.vue`
- `AppToggleButton`: segmented toggle button for selecting one option from a small set.
  Path: `resources/js/modules/shared/components/AppToggleButton.vue`
- `AppTitle`: shared heading primitive with semantic tag and size options.
  Path: `resources/js/modules/shared/components/AppTitle.vue`

- `AppFilterPanel`: panel de filtros inferior en móvil y modal centrada en escritorio; conserva el pie con limpiar y aplicar y admite validación de borradores.
  Path: `resources/js/modules/shared/components/AppFilterPanel.vue`
- `AppFilterOptions`: grupo de casillas tipadas en dos columnas para selección múltiple.
  Path: `resources/js/modules/shared/components/AppFilterOptions.vue`
- `AppFilterTrigger`: botón compacto con icono, contador y estado accesible del panel.
  Path: `resources/js/modules/shared/components/AppFilterTrigger.vue`
- `AppActiveFilters`: etiquetas removibles de filtros aplicados.
  Path: `resources/js/modules/shared/components/AppActiveFilters.vue`

- `AppThemeMenu`: botón con menú Claro, Oscuro y Sistema; comparte la preferencia persistida de Pinia entre autenticación y el encabezado de la aplicación.
  Path: `resources/js/modules/shared/components/AppThemeMenu.vue`

Barrel export:

- `resources/js/modules/shared/components/index.ts`

## Icons

When a feature needs icons, use `@heroicons/vue` as the default icon library.

## Bibliotecas de interfaz

- Usar PrimeVue 5 con el preset Aura adaptado y la configuración española de `resources/js/lib/primevue.ts`. Conservar el selector `.dark` y las capas CSS existentes.
- Reutilizar los adaptadores `App*` antes de importar un control directamente. `AppModal` conserva las acciones y formularios externos sobre PrimeVue Dialog; `AppInput` conserva la normalización de importes como cadenas.
- Usar `AppDatePicker` para fechas y `AppSearchSelect` para selección con búsqueda. No cambiar los tipos de los valores enviados a los mapeadores de API.
- Validar con Zod y PrimeVue Forms. Los formularios existentes usan `usePrimeForm`, basado en la API oficial `@primevue/forms/useform`, para conservar contratos tipados, validación cruzada, reinicio y estado de interacción. Los formularios locales pueden usar `Form` y `FormField` con `zodResolver`.
- Mantener un único propietario del estado de validación. Con `FormField`, sincronizar los adaptadores mediante `$field.props.onChange({ value: nextValue })` y reiniciar el formulario cuando cambie el registro o desafío representado.
- Usar `@primeui/vue-chart` y `@primeui/chart-style` para gráficos, con renderer SVG y tema PrimeOne. Conservar datos, cálculos, formatos monetarios y colores de cuentas.
- Mantener búsquedas, filtros, estados y scroll infinito de los composables existentes. Los componentes PrimeVue no sustituyen la lógica de dominio ni los permisos.
- Proteger también los manejadores de mutación contra envíos repetidos mientras guardan; deshabilitar únicamente el botón del modal no bloquea Enter dentro del formulario.

## Filtros de listas

- Reutilizar `AppFilterPanel`, `AppFilterOptions`, `AppFilterTrigger` y `AppActiveFilters` según [la guía de interfaz](../../docs/frontend/primevue.md#filtros-de-listas).
- Mantener borradores locales: seleccionar y limpiar no actualizan filtros aplicados hasta confirmar. Cerrar descarta los cambios pendientes.
- Conservar búsqueda, reglas del módulo, sincronización con URL y reinicio de paginación. Quitar una etiqueta se aplica directamente.
- Mantener el orden encabezado → buscador con botón compacto y contador → filtros activos → lista. En `AccountTransactionsActivity`, insertar las etiquetas en el slot `filters`, nunca antes del componente de actividad.
- El periodo de transacciones globales sigue siendo obligatorio; validar fechas y rango antes de aplicar y conservar el restablecimiento a mes actual.

## Maintenance Rule

Whenever a new reusable shared component is created, update this file in the same task so the index stays current.

Whenever a feature is implemented, prefer existing shared components first. Only create a new shared component when the pattern is clearly reusable or repeated.
