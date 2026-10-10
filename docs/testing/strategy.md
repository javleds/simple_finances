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

Los scripts Playwright capturan vistas para inspección; no constituyen una suite E2E. Consulta `../../scripts/playwright/README.md`. No incorporar capturas o archivos temporales a commits.

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

En navegador, revisa menús, diálogos, filtros, búsquedas y scroll infinito en móvil y escritorio, con temas claro y oscuro. Dentro de una cuenta, comprueba las cinco pestañas inferiores, su desplazamiento horizontal y que cada icono quede junto a su título. Confirma primero si se está sirviendo HMR o un build actualizado. Consulta [la migración y el alcance de su validación](../frontend/primevue.md#validación-realizada).

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
