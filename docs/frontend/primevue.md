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
| `AppModal` | Dialog; cierre, variantes, acciones y envío de formularios mediante botones del pie |
| `AppActionMenu`, `AppContextTabs` | Menu y Tabs; acciones y navegación contextual existentes |
| `AppCard` | Card; padding configurable desde el contenedor y contenido espaciado dentro del slot |

Las pestañas inferiores de cuentas presentan el icono y el título en una misma fila. El listado de pestañas conserva su desplazamiento horizontal en pantallas estrechas. Los mensajes de error, confirmación y estado usan componentes PrimeVue donde corresponde.

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
