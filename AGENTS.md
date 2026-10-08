# Arquitectura y trabajo en este repositorio

Este repositorio contiene Laravel en la raíz y Vue en `resources/js`. Mantener las responsabilidades: Laravel administra dominio, persistencia, permisos, JWT y contratos `/api`; Vue conserva router, Pinia, TanStack Query y módulos. No introducir Inertia ni desarrollar UI Filament.

La aplicación es mobile-first. Reutilizar componentes compartidos y consultar `resources/js/AGENTS.md`. Documentación: `docs/architecture/overview.md`, `docs/architecture/boundaries.md`, `docs/domain/shared-accounts.md`, `docs/shared-account-model.md`, `docs/operations/local-development.md` y `docs/operations/deployment.md`.

Desarrollo en host: `composer run dev`, aplicación en http://127.0.0.1:8000. PHP y Vite también pueden iniciarse por separado. Antes de completar frontend: `npm run type-check`. Validar comportamiento con `php artisan test`, `npm run test:unit -- --run` y `npm run build`.

No incluir dumps, capturas ni archivos temporales. Cada cambio completado debe tener commits convencionales en inglés. No ejecutar despliegues remotos sin autorización específica.

## Shared Account Domain Rules

The current finance model supports individual current accounts, shared current accounts with explicit custodianship, and shared receivable/payable accounts. Preserve these rules when changing transactions, account balances, reimbursements, or related UI:

- `account_member_ledger_entries` is a core domain table, not an auxiliary audit log. It drives member custody, settlements, pending reimbursements, transaction badges, account detail summaries, dashboard summaries, and the account ledger view.
- For shared accounts, `custody_by_user`, `settlements_by_user`, and `pending_reimbursements` must stay aligned with transaction mutations, member transfers, and account balance recalculation.
- A positive shared account balance must be explicitly custodied by one or more users. Avoid introducing states where positive money exists without a custodian.
- When a shared account balance is `<= 0`, paying an expense with account funds must be disabled/rejected. The user should register the expense as paid out of pocket.
- When a member pays out of pocket, that member's own share is considered covered. Only the uncovered shares owed by other members should remain pending.
- If user A records an expense but user B is marked as the payer, the reimbursement must still be actionable for the debtor/creditor pair derived from the ledger.
- In positive custodied accounts, if a non-custodian pays out of pocket, the custodian(s) should owe the payer according to available custodianship rules.
- Editing or deleting transactions must regenerate allocations, ledger entries, technical recovery transactions, balances, reimbursement badges, and pending reimbursement summaries without leaving stale rows.
- After creating, editing, deleting, or settling shared-account transactions in the SPA, invalidate or update account detail/list queries, dashboard queries, and transaction facility queries so actionable reimbursements appear without a manual reload.
- The account `Libro` view should present a user-readable timeline of balance, custody, settlements, allocations, and technical balance effects. Do not simplify it into a raw table dump.

Do not remove or bypass these rules as a cosmetic cleanup. If a change needs to alter them, update the backend tests first and make the product behavior explicit.


# Lineamientos para el proyecto
- Proyecto Laravel 12 con SPA Vue 3 + TypeScript integrada en resources/js; el código generado debe estar en inglés.
- No agregar comentarios en el codigo ni documentacion extra salvo que se pida de forma explicita.
- Seguir principios SOLID con enfoque en clases de servicio nombradas como acciones y, de ser posible, con un unico metodo publico.
- Comunicar controladores con servicios mediante DTOs cuando exista una responsabilidad clara.
- Usar inyeccion de dependencias siempre que se pueda; si no es viable, usar el helper `app(Class::class)`.
- Evitar if/else anidados y, en general, la sentencia `else`; preferir early returns.
- Sustituir switch/case por un patron Strategy con registro automatico en el contenedor de Laravel para manejar la logica por clases.
- Crear pruebas automatizadas para cada service class.
- Todos los documentos .md generados por codex CLI deben estar en español a menos que se indique lo contrario.
- Los modelos se configuran unguarded en AppServiceProvider; validar y autorizar escrituras explícitamente y conservar casts tipados.
- Genera codigo completamente tipado, en funciones, returnos, argumentos, etc.
- Usa short sytax disponible en las últimas versiones de PHP compatibles con este proyecto (PHP 8.2).

## Contexto funcional
- Aplicacion para finanzas personales: usuarios crean cuentas con transacciones de ingreso y egreso; el balance se calcula al registrar movimientos.
- Cuentas de tipo credito emulan tarjetas de credito; feature incompleto y requiere manejo especifico.
- Subscripciones: registrar servicios (Netflix, YouTube Premium, etc.), periodo y monto; generar proyecciones mensuales/anuales y sugerir ahorro mensual o quincenal para pagos anuales.
- Cuentas compartidas entre multiples usuarios: cada usuario puede editar/eliminar solo sus transacciones; otros usuarios las ven y reciben notificaciones por email en cada movimiento.
- Notificacion semanal: cada domingo se envia email con transacciones en CSV segun configuracion de notificaciones (por transaccion, por cuenta y por movimientos).
- Eloquent usa un scope global para filtrar por el usuario activo; todos los modelos tienen `user_id` salvo relaciones many-to-many y cuentas compartidas que se resuelven por joins.
- Orquestacion sobre eventos: para este proyecto, preferir clases de orquestacion explicita sobre eventos/listeners en flujos nuevos o refactors. Las clases de orquestacion (ej. `CreateTransaction`) llaman servicios para crear transaccion, recalcular balance y enviar notificacion sin contener logica de dominio. En otros proyectos, preguntar si se prefiere orquestacion o eventos y guardar la decision en las instrucciones locales del proyecto.
