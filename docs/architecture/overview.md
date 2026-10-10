# Arquitectura integrada

Laravel 12 contiene backend y frontend en un solo repositorio. Laravel entrega el HTML de `resources/views/spa.blade.php`, y `@vite` carga `resources/js/main.ts`. Vue Router gestiona navegación desde `/`; la base de assets compilados no determina las rutas del navegador.

Vue conserva módulos, componentes compartidos, TypeScript, Pinia para estado de cliente y TanStack Query para estado de servidor. Los repositorios llaman al cliente HTTP con `/api` por defecto y JWT Bearer. Laravel valida y autoriza cada petición y mantiene servicios, modelos Eloquent, migraciones e integraciones de correo, WhatsApp, Telegram y OpenAI. PrimeVue 5 aporta los controles compartidos y de los módulos, formularios, diálogos, menús y mensajes. Usa Aura adaptado en azul, Tailwind 4 y el selector `.dark`. PrimeVue Forms integra los esquemas Zod; el gráfico del dashboard usa PrimeUI Charts con renderer SVG. Consulta [la interfaz actual](../frontend/primevue.md) para los contratos que conservan los adaptadores y la cobertura de la migración.

El catch-all web sirve GET/HEAD de navegación, excluyendo API, healthcheck y assets. Nginx entrega archivos estáticos y devuelve 404 para assets inexistentes. Los endpoints y las reglas financieras no cambian por integrar el frontend.

Vite 8 con el plugin Laravel 3 compila una entrada Vue. En desarrollo Composer supervisa PHP, Vite y el worker de colas; en producción Node 24 construye assets y PHP/Nginx comparten `public/build` mediante el montaje existente. El historial frontend se importa sin squash, conservando ambos historiales.

WhatsApp vincula un teléfono al usuario autenticado; no incorpora un método de login. La recepción valida la firma de Meta y persiste el payload cifrado en `webhook_receipts` junto con el job en una transacción de la misma base de datos. Solo después del commit devuelve `200`. El worker actual valida el proveedor y el tipo de sobre y marca la recepción como procesada; todavía no genera respuestas, transacciones ni llamadas a OpenAI. Telegram conserva su procesamiento actual. Consulta [la integración de WhatsApp](../integrations/whatsapp.md).

`account_member_ledger_entries` sigue siendo parte del dominio: custodia, liquidaciones, reembolsos y balances deben permanecer sincronizados con las transacciones. Consulta `../shared-account-model.md` para las reglas completas.
