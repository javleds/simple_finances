# Pruebas y validación

Desde la raíz Laravel:

```bash
composer validate
npm run type-check
npm run test:unit -- --run
npm run build
php artisan test
composer format:check
php artisan route:cache
```

Backend usa PHPUnit/Pest y SQLite en memoria; frontend usa Vitest, Vue Test Utils y jsdom. Las pruebas Vue permanecen junto a sus módulos en `resources/js`. Vitest usa configuración sin el plugin Laravel para no escribir `public/hot`.

Verifica entrada HTML y rutas profundas; 404 de API desconocida y assets ausentes; `/api` predeterminado y override; redirección 401 a `/auth`; verificación firmada bajo el dominio original. Repite login, logout, dashboard, cuentas y reembolsos tanto con HMR como con build y Vite detenido.

Las reglas de cuentas compartidas requieren pruebas de balances, custodia, asignaciones y reembolsos al crear, editar, eliminar o liquidar transacciones. No sustituir estas verificaciones por capturas visuales.

`npm run lint` y `npm run format` modifican archivos. `composer stan` existe como script pero PHPStan no está declarado como dependencia; `composer pint` tampoco implica que Pint esté instalado. No presentar estos checks como ejecutados si faltan sus binarios.

Los scripts de captura Playwright sirven para inspección; los scripts `verifyMobileDesign.mjs` y `verifyVirtualBalanceChart.mjs` contienen aserciones con fixtures. Ninguno acredita todos los flujos privados contra servicios reales. Consulta [los scripts y sus requisitos](../../scripts/playwright/README.md). No incorporar capturas o archivos temporales a commits.

## WhatsApp

La cobertura enfocada se ejecuta con:

```bash
php artisan test --filter=WhatsApp
npm run test:unit -- --run resources/js/modules/settings/components/WhatsappConnectionCard.spec.ts
```

Verifica expiración de 24 horas, ceros iniciales, límites de solicitudes e intentos, conflictos de teléfonos, reenvío, fallos de envío y desvinculación. En el webhook, verifica firma, rechazo de eventos inválidos, persistencia y encolado atómicos, repetición del cuerpo, procesamiento y reintentos del worker. La UI debe conservar el aislamiento de datos al cambiar de sesión.

Las pruebas automatizadas sustituyen las llamadas a Meta; no acreditan entrega de mensajes ni aprobación de plantillas. La prueba real del OTP permanece pendiente según el procedimiento de [WhatsApp](../integrations/whatsapp.md).

## Interfaz PrimeVue

Además del chequeo de tipos y las pruebas completas, verifica fechas locales, importes durante la edición, validación Zod entre campos, reinicio al cambiar de registro, envío desde el pie del modal y bloqueo de envíos duplicados. Las regresiones enfocadas están en `primeControls.spec.ts`, `primeForms.spec.ts`, `AccountUserEditModal.spec.ts`, `useAccountGoalPageActions.spec.ts` y `WhatsappConnectionCard.spec.ts`.

En navegador, revisa menús, diálogos, filtros, búsquedas y scroll infinito en móvil y escritorio, con temas claro y oscuro. Dentro de una cuenta, comprueba las cinco pestañas bajo el encabezado móvil y encima de la navegación inferior desde `sm`, sin separación entre ambas barras fijas. Verifica desplazamiento horizontal y cada icono junto a su título. Confirma primero si se está sirviendo HMR o un build actualizado. Consulta [la migración y el alcance de su validación](../frontend/primevue.md#validación-realizada).

## Filtros de listas

Las regresiones están en:

| Área | Prueba |
| --- | --- |
| Borradores de cuentas | `AccountFiltersModal.spec.ts` |
| Botón compacto, contador y etiquetas | `AccountsToolbar.spec.ts` |
| Metas, invitaciones y movimientos de cuenta | `AccountSublistFilters.spec.ts` |
| Estado único y frecuencias de suscripciones | `SubscriptionFiltersModal.spec.ts` |
| Frecuencias de ingresos fijos | `DistributionRuleFiltersModal.spec.ts` |
| Periodos, reinicio y fechas inválidas | `TransactionFacilityFiltersModal.spec.ts` |
| Orden buscador → filtros activos → lista | `AccountTransactionsActivity.spec.ts` |

Se ejecutan con `npm run test:unit -- --run`. Las pruebas de borradores usan los componentes compartidos de filtros y sustituyen el contenedor modal cuando necesitan aislar el comportamiento.

Para revisar el flujo completo en navegador:

1. Abrir cada uno de los siete paneles en móvil y escritorio. Verificar pie visible, grupos alineados y ausencia de desbordamiento horizontal.
2. Cambiar opciones y comprobar que la lista y la URL permanecen intactas hasta aplicar. Cerrar y reabrir debe recuperar la selección aplicada.
3. Limpiar y aplicar; comprobar que se conserva la búsqueda. Quitar una etiqueta debe actualizar la lista, la URL y el contador.
4. En transacciones globales, comprobar un rango válido, uno invertido y una fecha inexistente. Restablecer el mes actual primero afecta al borrador; quitar la etiqueta de un periodo personalizado lo restablece directamente.
5. En transacciones anidadas, activar un tipo y verificar que las etiquetas estén debajo del buscador y antes del listado, sin intercalarse en el resumen de saldo, custodia o reembolsos.

La implantación se revisó en las siete vistas a anchos de 390 y 1440 píxeles. La corrección de ubicación se comprobó además en transacciones anidadas con un filtro aplicado. Pasaron las suites PHP y Vue, el chequeo de tipos, ESLint y el build; tras la corrección pasó también su prueba de regresión específica. Estas revisiones puntuales no sustituyen una suite E2E ni acreditan todos los flujos privados o temas. Las capturas temporales permanecen fuera de los commits.

## Tema, porcentajes y notificaciones

| Área | Regresión automatizada |
| --- | --- |
| Tema persistido y seguimiento del dispositivo | `resources/js/__tests__/theme.spec.ts`: preferencias explícitas, Sistema, cambios en vivo y limpieza de listeners |
| Distribución de miembros | `useAccountUsersSplitDraft.spec.ts`: aplicar, restablecer, error de guardado, lista incompleta y actualización de porcentajes guardados |
| Lectura y envío de notificaciones | `notificationSettingsRepository.spec.ts`: conversión de `checked`, conservación de identificadores activos y arreglos vacíos |
| Validación de notificaciones | `ApiEndpointsTest.php`: guardado, desactivación total y rechazo de campos omitidos; `EndpointAuthorizationTest.php`: filtro de cuentas visibles |

En navegador, comprobar el menú de tema en autenticación y antes de Perfil, persistencia al recargar y seguimiento del dispositivo. En Usuarios, modificar porcentajes, restablecer, aplicar y recargar; comprobar bloqueo con búsqueda o miembros pendientes de cargar. En Configuración, distinguir estados claros y oscuros, desactivar todas las preferencias de un grupo y comprobar recuperación del estado previo ante un fallo de guardado.

La revisión puntual del tema cubrió anchos de 320, 390 y 1440 píxeles, persistencia y cambios del dispositivo. Las notificaciones se revisaron en claro y oscuro a 390 píxeles; se simuló un error de guardado en el navegador para comprobar la recuperación del estado sin modificar preferencias reales. La aceptación de arreglos vacíos se verificó mediante pruebas de API. Estas comprobaciones no constituyen una suite E2E completa.

Tras estos cambios pasaron 197 pruebas backend, 108 frontend, chequeo de tipos, ESLint de los archivos modificados y build. Los totales describen esa ejecución y pueden crecer con cambios posteriores.

## Diseño móvil, documentos legales y cuentas virtuales

Con la aplicación local disponible en `http://127.0.0.1:8000` y assets actualizados:

```bash
npm run pw:mobile-design
node scripts/playwright/verifyVirtualBalanceChart.mjs
```

Ambos scripts usan sesiones ficticias y respuestas API interceptadas; bloquean escrituras y fallan ante solicitudes sin fixture. No necesitan credenciales reales. `PW_APP_URL` permite cambiar la URL local. Si Vite está detenido, ejecuta `npm run build` antes de probar. Se requiere Chromium disponible para Playwright.

| Verificación | Cobertura |
| --- | --- |
| `verifyMobileDesign.mjs` | 36 combinaciones de 360/390/430 px, alturas 640/844, claro/oscuro y tres flujos; más encabezado desktop: 37 casos |
| Formularios y paneles | Fullscreen de suscripción, selector con nombres y descripciones largos, sheet de distribución, límites del viewport, scroll, Escape y retorno de foco |
| Documentos legales | Lectura embebida sin modal, conservación de nombre/correo y casillas sin aceptar; cierre y foco; ambas páginas independientes y enlaces a nueva pestaña |
| `verifyVirtualBalanceChart.mjs` | 6 combinaciones de 360/390/430 px y claro/oscuro, con ganancias, pérdidas y saldo histórico |
| Barras virtuales | Segmentos en la misma fila, contiguos y con proporción 10000:500; totales y diferencias exactos; cambio a Físicas y recuperación ante error de consulta |
| `VirtualBalanceBreakdown.spec.ts` | Signos positivo/negativo/cero, referencias iniciales sin duplicación, diferencias sin reclasificar y redondeo en centavos |
| `virtualBalanceChart.spec.ts` | Cruce por cuenta y orden, total coherente con segmentos, pérdidas, diferencias históricas y resumen ausente |

Últimos resultados: 122 pruebas frontend en 40 archivos; 37 casos de diseño móvil y 6 casos de gráfica en ejecuciones separadas; TypeScript, build y ESLint correctos. La suite backend completa del refactor pasó 197 pruebas/1198 aserciones y la comprobación posterior enfocada de cuentas virtuales pasó 5 pruebas/28 aserciones.

La auditoría inicial revisó 24 vistas y 106 capturas con Chrome DevTools. Las capturas y galerías quedan fuera de Git. Los fixtures no acreditan pagos, liquidaciones, correcciones ni envíos de códigos reales; tampoco sustituyen una prueba de teclado y safe-area en dispositivo físico. El registro de validación y la deuda preexistente de Oxlint están en [la guía de interfaz](../frontend/primevue.md#validación-realizada).
