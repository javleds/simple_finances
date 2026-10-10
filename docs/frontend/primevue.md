# Interfaz con PrimeVue

## Alcance actual

La SPA Vue 3 integrada en Laravel usa PrimeVue 5 para los controles de autenticación, dashboard, cuentas, transacciones, libro, invitaciones, metas, usuarios, cuentas virtuales, suscripciones, distribución, perfil y configuración, incluida la vinculación de WhatsApp. Las páginas de contenido estático conservan su composición Vue.

La migración conserva los repositorios HTTP, esquemas y mapeadores de API, permisos, búsquedas, filtros, paginación con scroll infinito y reglas financieras. TanStack Query sigue administrando el estado del servidor y las invalidaciones; PrimeVue administra la presentación y la interacción de sus controles.

## Componentes y tema

`resources/js/main.ts` registra PrimeVue con el preset y la configuración española de `resources/js/lib/primevue.ts`. El preset deriva de Aura, usa azul como color principal y conserva `.dark` y las capas CSS de Tailwind 4. Heroicons sigue siendo la biblioteca de iconos de la aplicación.

Los componentes de `resources/js/modules/shared/components` adaptan PrimeVue a los contratos existentes:

| Adaptador | Componente y contrato conservado |
| --- | --- |
| `AppInput`, `AppPasswordInput` | InputText y Password; importes editables como cadenas y visibilidad de contraseña |
| `AppDatePicker` | DatePicker; fecha local `yyyy-MM-dd`, sin conversión de zona horaria, y vacío como `null` |
| `AppSearchSelect` | Select; búsqueda, limpieza, descripciones y asociación accesible de etiquetas y errores |
| `AppSwitch`, `AppToggleButton` | ToggleSwitch y SelectButton; valores y eventos del consumidor |
| `AppModal` | Dialog; cierre, variantes, acciones, pie personalizable y presentación inferior en móvil mediante `presentation="sheet"` |
| `AppActionMenu`, `AppContextTabs` | Menu y Tabs; acciones y navegación contextual existentes |
| `AppCard` | Card; padding configurable desde el contenedor y contenido espaciado dentro del slot |
| `AppFilterPanel` | Composición de `AppModal` y `AppButton`; formulario y pie fijo con limpiar y aplicar |
| `AppFilterOptions` | Casillas nativas tipadas, agrupadas en dos columnas y con toda la opción pulsable |
| `AppFilterTrigger` | Botón con icono y contador, nombre accesible y asociación con el panel |
| `AppActiveFilters` | Etiquetas removibles de selecciones aplicadas; conserva los callbacks de cada módulo |

Las pestañas inferiores de cuentas presentan el icono y el título en una misma fila. El listado de pestañas conserva su desplazamiento horizontal en pantallas estrechas. Los mensajes de error, confirmación y estado usan componentes PrimeVue donde corresponde.

## Filtros de listas

El patrón se aplica a cuentas, transacciones de una cuenta, metas, invitaciones de una cuenta, suscripciones, ingresos fijos y transacciones globales. Las listas que solo tienen búsqueda conservan su comportamiento.

### Presentación y ubicación

En móvil, el panel se abre desde abajo; desde el breakpoint `sm` se centra como modal. El contenido puede desplazarse y el pie permanece visible, con espacio para el área segura del dispositivo. El botón que abre el panel conserva únicamente el icono y el contador cuando hay filtros activos; el nombre accesible comunica su propósito.

El orden de cada listado es: encabezado, buscador con botón de filtros, etiquetas activas y lista. Los resúmenes financieros y acciones de reembolso pueden preceder al listado. En transacciones anidadas, las etiquetas se insertan mediante el slot `filters` de `AccountTransactionsActivity`, después de su toolbar; no deben colocarse antes del componente de actividad.

### Borrador y aplicación

- Abrir copia las selecciones aplicadas a un borrador local.
- Seleccionar opciones o limpiar modifica solo el borrador; no cambia la URL ni recarga la lista.
- **Aplicar filtros** confirma todas las selecciones juntas y cierra el panel.
- Cerrar con la cruz, Escape o el fondo descarta el borrador. Al volver a abrir se recuperan los filtros aplicados.
- Quitar una etiqueta modifica directamente el filtro aplicado y actualiza la lista.
- Aplicar o limpiar filtros conserva el texto del buscador. Los composables mantienen la sincronización con la URL y los cargadores reinician la paginación cuando cambian los filtros aplicados.

Cada módulo conserva sus tipos y reglas: el estado de suscripciones admite una sola selección; sus frecuencias y los grupos multiselección conservan sus combinaciones existentes. Los filtros no cambian permisos ni contratos HTTP.

### Periodo de transacciones globales

El periodo inicial es el mes actual. **Mes actual** restablece solo las fechas del borrador y requiere aplicar para confirmar. Se rechazan fechas inexistentes, fechas vacías y rangos invertidos; el botón de aplicar queda deshabilitado y el envío del formulario también se protege.

Un periodo distinto al mes actual aparece como una etiqueta y cuenta como un filtro. Quitar esa etiqueta restablece el mes actual. El periodo predeterminado muestra «Periodo: mes actual» sin etiqueta removible ni contador. `AppDatePicker` conserva fechas locales `yyyy-MM-dd`; `transactionPeriod.ts` centraliza el rango inicial y la validación.

### Responsabilidades

`AppFilterPanel` emite `apply`, `clear` y `close`, admite `applyDisabled` y no administra las selecciones. Cada modal de módulo es dueño de su borrador. `AppFilterOptions` recibe opciones tipadas y un arreglo mediante `v-model`. `AppActiveFilters` recibe objetos `ActiveFilter` con `key`, `label` y `remove`; los callbacks pertenecen al módulo.

El índice de componentes está en [las instrucciones de frontend](../../resources/js/AGENTS.md). La cobertura automatizada y las comprobaciones visuales se describen en [la estrategia de pruebas](../testing/strategy.md#filtros-de-listas).

## Formularios y Zod

`resources/js/modules/shared/composables/usePrimeForm.ts` integra la API oficial `@primevue/forms/useform` y `zodResolver`. Los composables de formulario conservan sus esquemas, valores iniciales, mapeadores y reglas condicionales. La validación tiene un único propietario e incluye errores cruzados entre campos, estado de envío y reinicio.

Los formularios existentes mantienen su elemento `form` y sus identificadores para permitir envío desde el pie de un modal. Los formularios locales de porcentajes de miembros, reparación de custodia, snapshots virtuales y WhatsApp usan `Form` y `FormField` de PrimeVue. Al integrar un adaptador con `FormField`, el cambio se comunica mediante `$field.props.onChange({ value: nextValue })`.

Al cambiar el registro editado o el desafío de WhatsApp se reinicia el formulario correspondiente. Los manejadores de mutación también bloquean envíos repetidos mientras guardan, incluido Enter dentro del formulario.

## Gráficos y dependencias

`DashboardBalanceChart.vue` usa `@primeui/vue-chart` y `@primeui/chart-style`, con renderer SVG y tema PrimeOne. Conserva los balances con signo, colores de cuenta y formatos monetarios MXN. La categoría interna usa el identificador de cuenta para distinguir cuentas con el mismo nombre.

La licencia se configura mediante `VITE_PRIMEVUE_LICENSE_KEY`; los cambios requieren reiniciar Vite o recompilar. Se retiraron `vee-validate`, `@vee-validate/zod`, `@vueform/multiselect`, `@vuepic/vue-datepicker`, `echarts` y `vue-echarts`.

## Validación realizada

En la migración pasaron 69 pruebas frontend en 25 archivos, 192 pruebas backend con 1168 assertions, chequeo de tipos, ESLint, Oxlint y build. Las pruebas de regresión cubren importes, fechas, reparto porcentual, accesibilidad de selectores, acciones externas de modales, validación cruzada, reinicio y protección contra envíos repetidos.

La revisión visual pública cubrió 32 combinaciones de páginas, tamaños móvil/escritorio y temas claro/oscuro, sin errores de página ni desbordamiento horizontal. Posteriormente se comprobó con Chrome DevTools el menú inferior dentro de una cuenta autenticada: las cinco pestañas tienen icono y título alineados en escritorio y móvil, sin desbordamiento de la página. Esta revisión puntual no equivale a validar todos los flujos privados de extremo a extremo.

Para repetir los chequeos y revisar vistas consulta [pruebas](../testing/strategy.md). Para distinguir HMR de assets compilados consulta [desarrollo local](../operations/local-development.md#assets-de-desarrollo-y-compilados).
