# Cuentas compartidas

Este documento resume reglas de dominio que deben mantenerse visibles para cambios futuros. La fuente detallada principal es `docs/shared-account-model.md`.

## Terminologia observada

- Cuenta individual: cuenta corriente de un usuario.
- Cuenta compartida: cuenta con membresias en `account_user` y porcentajes por miembro.
- Transaccion economica: registro en `transactions` que afecta el balance cuando esta completado.
- Asignacion: responsabilidad de un gasto compartido registrada en `transaction_allocations`.
- Ledger de miembros: registros en `account_member_ledger_entries` que derivan custodia, liquidaciones y reembolsos.
- Transferencia entre miembros: movimiento que liquida settlement entre dos usuarios de la misma cuenta.

Confianza: Observado en `docs/shared-account-model.md`, modelos, migraciones y servicios.

## Reglas observadas

### Las transacciones nuevas deben quedar completadas

Regla: las escrituras operativas de transacciones usan `completed`; `pending` ya no es el estado operativo para planear gastos.

Evidencia: `TransactionCreator` rechaza estados distintos a `Completed`; `docs/shared-account-model.md` documenta el cambio.

Confianza: Observado.

### El balance de cuenta es economico

Regla: el balance se deriva de ingresos completados menos egresos completados. Las deudas entre miembros no crean ingresos reales.

Evidencia: `Account::getBalanceUntilNow()`, `RecalculateAccountBalance` y `docs/shared-account-model.md`.

Confianza: Observado.

### El ledger de miembros es dato de dominio

Regla: `account_member_ledger_entries` no es un audit log auxiliar; de ahi salen custodia, settlements, reembolsos pendientes, badges y vistas de libro.

Evidencia: `BuildAccountMemberSummary`, `BuildPendingReimbursementItems`, `BuildAccountLedgerTimeline`, `SyncAccountMemberLedger`, `RegisterAccountMemberTransfer`, tests de servicios y `AGENTS.md`.

Confianza: Observado.

### Gastos de bolsillo generan settlement

Regla: cuando un miembro paga un egreso de bolsillo, el pagador recibe una entrada positiva `expense_paid` y cada asignacion genera `expense_share` negativa. La parte propia del pagador queda cubierta por su propia asignacion.

Evidencia: `SyncAccountMemberLedger::recordOutcome()` y `docs/shared-account-model.md`.

Confianza: Observado.

### Gastos desde fondo de cuenta consumen custodia

Regla: cuando el egreso usa `account_fund`, el ledger registra `account_fund_expense` negativo por asignacion o, si no hay asignaciones, por el pagador resuelto.

Evidencia: `SyncAccountMemberLedger::recordOutcome()`.

Confianza: Observado.

### Transferencias entre miembros liquidan settlement

Regla: una transferencia entre miembros registra movimientos de settlement (`settlement_transfer`) para reducir o cerrar deuda. No debe crear movimientos de custodia (`internal_transfer`) ni ingresos tecnicos; si la custodia debe cambiar, debe registrarse como una operacion explicita de custodia.

Evidencia: `RegisterAccountMemberTransfer` y `docs/shared-account-model.md`.

Confianza: Observado.

### Migraciones historicas evitan doble conteo

Regla: las transacciones hijas migradas se marcan con `legacy_migrated_at` y las consultas operativas deben excluirlas.

Evidencia: migraciones `2026_07_19_*`, tests de migracion y `docs/shared-account-model.md`.

Confianza: Observado.

## Reglas inferidas que requieren confirmacion

- La SPA debe invalidar o refrescar todas las queries relacionadas con cuenta, dashboard y facility despues de mutaciones de transacciones compartidas. Esta regla esta documentada en `AGENTS.md` y hay queries por modulo, pero cada nueva mutacion debe verificarse caso por caso.
- La cuenta de credito aparece como feature incompleto en `api/AGENTS.md`; cualquier cambio en tarjetas debe tratarse como zona de riesgo hasta confirmar comportamiento esperado.

## Rutas importantes

- `docs/shared-account-model.md`
- `app/Services/Transaction`
- `app/Services/Accounts`
- `app/Models/AccountMemberLedgerEntry.php`
- `app/Models/TransactionAllocation.php`
- `database/migrations/2026_07_19_132651_create_transaction_allocations_table.php`
- `database/migrations/2026_07_19_132656_create_account_member_ledger_entries_table.php`
- `tests/Feature/Api/SharedAccountTransactionLifecycleTest.php`
- `tests/Feature/Services/Accounts`
- `tests/Feature/Services/Transaction`
- `resources/js/modules/accounts`
- `resources/js/modules/transactions`
