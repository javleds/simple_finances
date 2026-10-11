# Interfaz con PrimeVue

## Alcance actual

La SPA Vue 3 integrada en Laravel usa PrimeVue 5 para los controles de autenticación, dashboard, cuentas, transacciones, libro, invitaciones, metas, usuarios, cuentas virtuales, suscripciones, distribución, perfil y configuración, incluida la vinculación de WhatsApp. Las páginas de contenido estático conservan su composición Vue.

La migración conserva los repositorios HTTP, esquemas y mapeadores de API, permisos, búsquedas, filtros, paginación con scroll infinito y reglas financieras. TanStack Query sigue administrando el estado del servidor y las invalidaciones; PrimeVue administra la presentación y la interacción de sus controles.

## Componentes y tema

`resources/js/main.ts` registra PrimeVue con el preset y la configuración española de `resources/js/lib/primevue.ts`. El preset deriva de Aura, usa petróleo y neutros como paleta principal y conserva `.dark` y las capas CSS de Tailwind 4. Heroicons sigue siendo la biblioteca de iconos de la aplicación.

Los componentes de `resources/js/modules/shared/components` adaptan PrimeVue a los contratos existentes:

| Adaptador | Componente y contrato conservado |
| --- | --- |
| `AppInput`, `AppPasswordInput` | InputText y Password; importes editables como cadenas y visibilidad de contraseña |
| `AppDatePicker` | DatePicker; fecha local `yyyy-MM-dd`, sin conversión de zona horaria, y vacío como `null` |
| `AppSearchSelect` | Select; búsqueda, limpieza, descripciones y asociación accesible de etiquetas y errores |
| `AppSwitch`, `AppToggleButton` | ToggleSwitch y SelectButton; valores y eventos del consumidor |
| `AppModal` | Dialog; cierre, variantes, acciones, pie personalizable y presentaciones `dialog`, `sheet` y `fullscreen`; cuerpo desplazable y pie fijo |
| `AppThemeMenu` | Menu; selección persistida de Claro, Oscuro o Sistema desde los layouts |
| `AppActionMenu`, `AppContextTabs` | Menu y Tabs; acciones y navegación contextual existentes |
| `AppCard` | Card; padding configurable desde el contenedor y contenido espaciado dentro del slot |
| `AppFilterPanel` | Composición de `AppModal` y `AppButton`; formulario y pie fijo con limpiar y aplicar |
| `AppFilterOptions` | Casillas nativas tipadas, agrupadas en dos columnas y con toda la opción pulsable |
| `AppFilterTrigger` | Botón con icono y contador, nombre accesible y asociación con el panel |
| `AppActiveFilters` | Etiquetas removibles de selecciones aplicadas; conserva los callbacks de cada módulo |

Las cinco pestañas de cuenta presentan icono y título en una misma fila y conservan desplazamiento horizontal. En móvil quedan bajo el encabezado, con posición sticky. Entre `sm` y `lg` quedan fijas inmediatamente encima de la navegación principal: el desplazamiento usa `--app-bottom-nav-height`, sin margen inferior adicional. Desde `lg` vuelven al flujo de la página, debajo del encabezado de la cuenta. Los mensajes de error, confirmación y estado usan componentes PrimeVue donde corresponde.

## Sistema visual móvil

El sistema centraliza colores, radios y alturas en `resources/js/main.css` y adapta el preset Aura. La escala de espacio es 4/8/12/16/24/32 px: margen de página y padding habitual de tarjeta de 16 px, campos separados 16 px y secciones 24 px. Se evita acumular superficies y padding para grupos anidados.

| Elemento | Regla vigente |
| --- | --- |
| Tarjetas, campos, botones y etiquetas | Radio de 4 px mediante `--app-radius-control`; sin etiquetas en forma de píldora |
| Modales y paneles | Radio de 6 px mediante `--app-radius-overlay`; fullscreen móvil conserva esquinas rectas |
| Avatares e indicadores de carga | Forma circular conservada |
| Controles principales / botones de icono | 48 px / área táctil de 44 px |
| Títulos de página / sección | 24/30 px y 18/24 px; `AppSectionBar` admite `h1`, `h2` y `h3` |
| Acciones | Guardar/crear primarios, cerrar/cancelar neutrales, eliminar con variante `danger` |
| Formularios | Largos en fullscreen móvil, cortos en sheet, confirmaciones compactas en dialog; desde `sm` se centran |

El shell reserva el espacio de la navegación global y las áreas seguras mediante `--app-header-height` y `--app-bottom-nav-height`. El viewport incluye `viewport-fit=cover` e `interactive-widget=resizes-content`; esto no sustituye una prueba de teclado en dispositivo físico.

`TransactionListItem` alinea el menú de acciones sin aumentar la altura de la fila del concepto; conserva concepto, importe, autor, fecha y estados de reembolso. En distribución amplia, autor y fecha comparten fila. Las transacciones dentro de una cuenta se separan 8 px. `AppSearchSelect` limita el overlay al viewport y permite ajustar las descripciones en varias líneas. `AppLoadMoreFooter` presenta el estado y reintento sin una tarjeta adicional.

## Layout adaptable y navegación

`AdminLayout.vue` conserva la presentación móvil por debajo de **1024 px** (`lg`), con contenido de hasta 430 px y navegación inferior. Desde 1024 px usa una barra lateral de 240 px, encabezado desplazado a la derecha y contenido de hasta 1440 px. El cambio de tamaño reutiliza las mismas páginas y formularios; no crea una segunda instancia del estado.

La navegación principal tiene cinco entradas en ambos tamaños: **Inicio, Cuentas, Ahorro, Suscripciones y Más**. En móvil, Suscripciones se abrevia **Subs**. Inicio siempre ocupa la primera posición.

| Opción de Más | Destino |
| --- | --- |
| Distribución | `/admin/distribution` |
| Pagos | `/admin/settings/utilities/credit-card-payoff`, calculadora de pago de tarjetas |
| Configuración | `/admin/settings` |

En móvil, Más abre un `AppModal` con presentación `sheet`; en escritorio despliega los enlaces dentro de la barra lateral. El botón comunica su estado con `aria-expanded` y permanece destacado en las rutas de Distribución y Configuración, incluidas sus utilidades. El panel o desplegable se cierra al cambiar de ruta o cruzar el breakpoint de escritorio. Perfil, Invitaciones y Salir permanecen en el menú del usuario.

### Tarjetas de Dashboard y Ahorro

En escritorio, el dashboard coloca los indicadores de cuentas a todo el ancho y apila las demás tarjetas en dos columnas CSS, con separación de 24 px y `break-inside-avoid`. Esta composición tipo masonry evita igualar la altura de las filas y conserva cada tarjeta completa. Las columnas se leen de arriba abajo; no garantizan un orden horizontal por filas. En móvil se conserva el orden: balance, indicadores de cuentas, error de transferencia si existe, reembolsos, planeación de suscripciones y resumen del periodo.

Ahorro usa dos columnas independientes en escritorio: resumen e Historial observado se apilan a la izquierda, con 20 px entre ambos; Apartados ocupa la derecha. La altura de la lista de apartados no desplaza el historial. En móvil se conserva resumen → apartados → historial. No se aplica masonry a todas las listas de la aplicación: cuentas, metas y suscripciones conservan sus cuadrículas adaptables.

Los ajustes reutilizan `AppCard`, `AppButton`, `AppLink` y `AppModal`, sin dependencias adicionales ni cambios en cálculos, endpoints o permisos. Consulta [la cobertura adaptable](../testing/strategy.md#diseño-móvil-documentos-legales-y-cuentas-virtuales).

## Documentos legales y registro

Las páginas independientes son `/auth/terms-and-conditions` y `/auth/privacy-policy`, con rutas `auth.terms` y `auth.privacy`. Ambas incluyen un enlace de regreso al registro.

En registro, `RegisterLegalPreview` integra los mismos componentes de contenido dentro del formulario, sin modal ni navegación. Abrir o cerrar conserva los datos escritos y no acepta las casillas legales. El foco pasa al documento al abrir y vuelve al enlace al cerrar. **Abrir página completa (nueva pestaña)** permite consultar la ruta independiente sin abandonar el borrador. Los títulos usan `h1` en las páginas y `h2`/`h3` en el contenido embebido. Los textos legales no se modificaron.

## Selector de tema

`AppThemeMenu` aparece arriba a la derecha en autenticación y antes del botón de Perfil en el encabezado autenticado. Sustituye los selectores de los formularios de autenticación; Configuración ya no incluye una sección de tema visual.

El menú ofrece **Claro**, **Oscuro** y **Sistema** y muestra la opción seleccionada. `stores/theme.ts` conserva la preferencia en `localStorage` bajo `theme-mode` y restaura las preferencias explícitas existentes. Sin una preferencia válida usa Sistema. Esta opción sigue los cambios de apariencia del dispositivo en vivo; Claro y Oscuro prevalecen sobre el dispositivo. El tema resuelto actualiza `.dark` y `color-scheme`.

## Porcentajes predeterminados de miembros

En Usuarios de una cuenta, el propietario puede ajustar la distribución con `AppPercentageSplitEditor`. El borrador permanece separado de los porcentajes guardados: **Aplicar** envía toda la distribución a `PUT /api/accounts/{account}/users`; **Restablecer** descarta las modificaciones pendientes y recupera la última distribución guardada.

El endpoint existente exige exactamente todos los miembros y una suma de 100.00 tras normalizar a dos decimales. La interfaz bloquea el editor y la aplicación cuando hay búsqueda o faltan páginas por cargar. Mientras guarda bloquea los controles y los envíos repetidos. Un fallo conserva el borrador y muestra el error; un éxito actualiza los porcentajes de la lista sin perder sus importes de custodia y liquidación.

## Preferencias de notificaciones

Configuración distingue cada preferencia con un selector, texto **Activada/Desactivada** y borde y fondo destacados cuando está activa. Los cambios se guardan automáticamente y los controles se deshabilitan durante el envío. Si falla, se recupera el estado previo y el mensaje de error permanece visible.

`GET /api/notification-settings` devuelve la selección en `checked`; el repositorio la convierte al booleano `enabled` utilizado por la interfaz. `PUT /api/notification-settings` recibe `notification_type_ids` y `account_ids`: ambos campos deben estar presentes y ser arreglos, pero admiten listas vacías para desactivar todas las preferencias de su grupo. Omitir un campo sigue produciendo 422. El servidor conserva el filtro de cuentas visibles para el usuario.

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

### Formulario de transacciones según los miembros de la cuenta

`TransactionsForm.vue` adapta los controles al número de miembros recibido en `accountUsers`: muestra las opciones compartidas cuando hay más de uno y asigna los valores individuales automáticamente cuando hay exactamente uno.

| Control | Cuenta con un miembro | Cuenta con varios miembros |
| --- | --- | --- |
| Pagado por | Oculto; se asigna el único miembro | Visible; al crear sin valores iniciales se preselecciona el usuario actual si pertenece a la cuenta |
| Custodia del ingreso | Oculta; se asigna el único miembro | Visible como «¿Quién recibió el dinero?» |
| Fuente del pago | Oculta; se usa `account_fund` | Visible como «¿Con qué dinero se pagó?» |
| Reparto del egreso | Desactivado y oculto | Disponible mediante «Dividir entre usuarios de la cuenta» |

Las opciones de fuente de pago se presentan como **Dinero de la cuenta** (`account_fund`) y **Dinero personal** (`member_out_of_pocket`). La distinción permite registrar el uso de fondos comunes o un adelanto personal y conserva las reglas de custodia y reembolsos descritas en [cuentas compartidas](../domain/shared-accounts.md).

Para nuevos egresos compartidos, cuando el saldo conocido es cero o negativo, el formulario selecciona **Dinero personal**, deshabilita **Dinero de la cuenta** y muestra: «La cuenta no tiene saldo disponible. Registra el pago con dinero personal». Un saldo desconocido (`null`) no activa esta selección automática.

Al editar una transacción compartida existente, el saldo actual no sustituye automáticamente su fuente de pago original. En cuentas con un solo miembro, tanto la creación como la edición normalizan los campos ocultos al único miembro, la fuente a `account_fund` y el reparto a desactivado. La meta financiera permanece disponible para ingresos.

El cambio reutiliza `AppSearchSelect`, `AppToggleButton` y `AppText`; conserva los contratos API y las reglas del backend. `TransactionsForm.spec.ts` cubre los controles ocultos, los valores enviados en cuentas individuales, la selección del usuario actual, los saldos cero y negativos y la conservación de la fuente al editar movimientos compartidos.

## Gráficos y dependencias

`DashboardBalanceChart.vue` usa `@primeui/vue-chart` y `@primeui/chart-style`, con renderer SVG y tema PrimeOne. Las barras son horizontales, su altura se adapta al número de cuentas y los identificadores internos distinguen nombres repetidos. Los nombres completos y los importes MXN se conservan en la lista y los tooltips; solo los nombres muy largos del eje se abrevian.

- **Físicas:** una barra de balance por cuenta, conservando su color.
- **Virtuales:** una barra apilada por cuenta, con **ahorro neto** en petróleo y **rendimiento registrado** en verde. El ancho de cada segmento es proporcional a su importe; no se agrandan rendimientos pequeños para hacerlos más visibles. Un rendimiento cero no ocupa ancho.
- Los segmentos negativos se representan a la izquierda del cero; un rendimiento negativo usa rojo. El total numérico es la suma algebraica, no el ancho combinado a ambos lados del cero.
- Si ahorro y rendimiento no explican todo el total, aparece un segmento gris **Sin desglose**, también identificado en los importes. No se atribuye automáticamente a ganancias ni a aportaciones.

`DashboardBalanceSection` solicita el desglose a `GET /api/virtual-accounts` solo cuando se selecciona Virtuales. La consulta TanStack Query usa `['dashboard', 'virtual-balances']`, se refresca al volver a habilitarse y contempla carga, error y reintento. Pertenece al prefijo de invalidaciones existente del dashboard. No se cambiaron endpoints ni reglas financieras.

`buildVirtualBalanceChart` cruza los identificadores de la gráfica con el resumen virtual, conserva el orden y usa el total del mismo resumen que sus segmentos. Si una cuenta no tiene resumen, su saldo se muestra sin desglose, sin inventar ahorro o rendimiento.

La licencia se configura mediante `VITE_PRIMEVUE_LICENSE_KEY`; los cambios requieren reiniciar Vite o recompilar. Se retiraron `vee-validate`, `@vee-validate/zod`, `@vueform/multiselect`, `@vuepic/vue-datepicker`, `echarts` y `vue-echarts`.

## Resumen de cuentas virtuales

La página de cuentas virtuales conserva el **Total actual** y muestra **Ahorro neto** y **Rendimiento registrado** tanto en el resumen general como en cada cuenta. `VirtualBalanceBreakdown` unifica etiquetas, signos, colores y diferencias sin clasificar. Aportado, Retirado, Inicial, último corte, historial y captura de saldo permanecen disponibles.

| Concepto | Cálculo vigente |
| --- | --- |
| Ahorro neto | `netCapital`: ingresos manuales menos egresos manuales, según el resumen existente |
| Rendimiento registrado | `observedYield`: suma de los deltas de cortes capturados; puede incluir pérdidas o ajustes, no acredita ganancias realizadas |
| Saldo sin desglose | Total actual menos ahorro neto menos rendimiento registrado, conciliado en centavos |

El saldo inicial es una referencia del primer corte; no se suma otra vez a las aportaciones. Los movimientos técnicos de snapshots y los movimientos marcados como migrados están excluidos del cálculo manual existente. El desglose conserva esa regla y explicita diferencias históricas en lugar de reclasificar datos.


## Validación realizada

Los ajustes de escritorio y navegación de octubre de 2026 pasaron type-check, build, ESLint de los componentes modificados, **126 pruebas frontend** y **224 pruebas backend con 1232 aserciones**. La última ejecución de `PW_DESKTOP_ONLY=1 npm run pw:mobile-design` pasó **12 casos adaptables**: 390, 1023, 1024, 1280, 1440 y 1920 px en claro y oscuro. Incluye geometría de Dashboard y Ahorro con listas largas, navegación Más, ausencia de desbordamiento y conservación del borrador al redimensionar. Esta ejecución no repitió la matriz completa de formularios móviles y documentos legales.

La ejecución previa de los ajustes móviles pasó **122 pruebas frontend en 40 archivos**, TypeScript, build y ESLint de los módulos modificados. La suite backend completa pasó **197 pruebas y 1198 aserciones** durante el refactor inicial; posteriormente pasaron las **5 pruebas enfocadas de cuentas virtuales, con 28 aserciones**, sin cambios backend.

Las regresiones con fixtures pasaron 37 casos de diseño móvil/documentos legales y 6 de barras virtuales apiladas. El segundo script verifica geometría y proporciones de los segmentos, totales, pérdidas, diferencias históricas, alternancia Físicas/Virtuales y reintento. Consulta [comandos y alcance de pruebas](../testing/strategy.md#diseño-móvil-documentos-legales-y-cuentas-virtuales).

Durante el refactor inicial se comparó Oxlint contra HEAD: 12 errores preexistentes y cero nuevos en esa comparación. Este resultado corresponde a esa ejecución, no acredita una ejecución posterior de Oxlint sobre todos los cambios.

### Registro de la migración inicial

En la migración pasaron 69 pruebas frontend en 25 archivos, 192 pruebas backend con 1168 assertions, chequeo de tipos, ESLint, Oxlint y build. Las pruebas de regresión cubren importes, fechas, reparto porcentual, accesibilidad de selectores, acciones externas de modales, validación cruzada, reinicio y protección contra envíos repetidos.

La revisión visual pública cubrió 32 combinaciones de páginas, tamaños móvil/escritorio y temas claro/oscuro, sin errores de página ni desbordamiento horizontal. Posteriormente se comprobó con Chrome DevTools el menú inferior dentro de una cuenta autenticada: las cinco pestañas tienen icono y título alineados en escritorio y móvil, sin desbordamiento de la página. Esta revisión puntual no equivale a validar todos los flujos privados de extremo a extremo.

Para repetir los chequeos y revisar vistas consulta [pruebas](../testing/strategy.md). Para distinguir HMR de assets compilados consulta [desarrollo local](../operations/local-development.md#assets-de-desarrollo-y-compilados).
