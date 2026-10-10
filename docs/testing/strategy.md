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
