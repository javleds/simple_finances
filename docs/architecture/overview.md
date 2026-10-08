# Arquitectura integrada

Laravel 12 contiene backend y frontend en un solo repositorio. Laravel entrega el HTML de `resources/views/spa.blade.php`, y `@vite` carga `resources/js/main.ts`. Vue Router gestiona navegación desde `/`; la base de assets compilados no determina las rutas del navegador.

Vue conserva módulos, componentes compartidos, TypeScript, Pinia para estado de cliente y TanStack Query para estado de servidor. Los repositorios llaman al cliente HTTP con `/api` por defecto y JWT Bearer. Laravel valida y autoriza cada petición y mantiene servicios, modelos Eloquent, migraciones e integraciones de correo, Telegram y OpenAI.

El catch-all web sirve GET/HEAD de navegación, excluyendo API, healthcheck y assets. Nginx entrega archivos estáticos y devuelve 404 para assets inexistentes. Los endpoints y las reglas financieras no cambian por integrar el frontend.

Vite 8 con el plugin Laravel 3 compila una entrada Vue. En desarrollo Composer supervisa PHP y Vite; en producción Node 24 construye assets y PHP/Nginx comparten `public/build` mediante el montaje existente. El historial frontend se importa sin squash, conservando ambos historiales.

`account_member_ledger_entries` sigue siendo parte del dominio: custodia, liquidaciones, reembolsos y balances deben permanecer sincronizados con las transacciones. Consulta `../shared-account-model.md` para las reglas completas.
