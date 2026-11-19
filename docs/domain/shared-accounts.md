# Cuentas compartidas

Las rutas de código indicadas aquí son relativas a `apps/finance`, salvo referencias explícitas al paquete `packages/finance-domain` o a `docs`. Los modelos y permisos compartidos se describen en [límites arquitectónicos](../architecture/boundaries.md).

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

### Los movimientos personales no generan aportaciones pendientes

Cada movimiento nuevo conserva `uses_shared_account_rules`: `false` si la cuenta tiene un solo miembro y `true` si tiene varios al crearlo. En modo personal, el gasto usa `account_fund`, pertenece al único miembro y registra el importe completo como `account_fund_expense`, aunque el saldo sea cero o negativo. No genera asignaciones, deuda, aportaciones pendientes ni ingresos técnicos de recuperación. Los ingresos mantienen su registro de custodia.

Agregar o retirar miembros no cambia esa clasificación. Editar dentro de la misma cuenta conserva las reglas del movimiento; moverlo a otra cuenta aplica las reglas del destino. Si una cuenta vuelve a tener un miembro, los nuevos movimientos son personales y las obligaciones compartidas anteriores permanecen. Al editar movimientos anteriores pueden conservarse pagadores y asignaciones de antiguos miembros, sin permitir asignar nuevos participantes ajenos.

Los movimientos existentes conservan `null` y su comportamiento histórico. No se reparan ni reclasifican datos anteriores. Este indicador financiero es independiente del catálogo compartido de categorías.

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

## Custodios virtuales

Las cuentas corrientes compartidas pueden tener custodios virtuales propios. Son identidades financieras con `users.virtual_account_id`, sin membresía en `account_user`, autenticación, invitaciones, notificaciones ni porcentaje de reparto. Todos los miembros vigentes pueden crear, renombrar y eliminar custodios virtuales, consultar la custodia y registrar pagos y reembolsos. La vista Usuarios los presenta junto a los miembros, con acciones para renombrarlos o eliminarlos. Renombrar solo actualiza el nombre del custodio activo, sin modificar saldos, ledger ni descripciones históricas.

Crear permite añadir saldo mediante un ingreso marcado `virtual_custodian_opening`, excluido del disponible personal, y trasladar custodia existente con pares `internal_transfer`. Las aportaciones ordinarias posteriores conservan las reglas de dinero personal. Los traslados no aumentan el balance y solo disponen de custodia libre de reembolsos comprometidos.

Los movimientos nuevos conservan `uses_virtual_custodian_rules`; editar dentro de la misma cuenta mantiene ese modo histórico. En este flujo, la cobertura de gastos de bolsillo se debe al pagador efectivo, descontando compromisos de custodia anteriores. La parte sin cobertura se reparte entre personas; un pagador virtual puede adelantar más que su custodia. `virtual_custody_reimbursement_due` y `virtual_custody_settlement_transfer` separan estas obligaciones de las deudas entre miembros, también cuando ambas existen en un movimiento.

Eliminar exige distribuir exactamente el 100% entre miembros vigentes y resolver antes todas las obligaciones. `virtual_retired_at` retira la opción sin borrar el historial. Una edición o eliminación histórica que devuelve custodia o deudas a esa identidad la reactiva, dentro de la misma operación, para que la posición permanezca visible.

API: GET/POST `/api/accounts/{account}/custodians`; DELETE `/api/accounts/{account}/custodians/{custodian}`. Los resúmenes incluyen `virtual_custodians` separado de `users`; nunca usar la lista financiera para conceder acceso o calcular porcentajes de personas.

## Categorías compartidas

La cuenta conserva un catálogo propio desde su primera conversión a compartida, incluso si vuelve a tener un solo miembro. Se copian las categorías usadas en esa cuenta sin modificar los catálogos personales. Todos los miembros pueden crear y seleccionar; solo el propietario renombra o elimina. Eliminar puede reclasificar movimientos de otros miembros y exige decidir su destino si hay uso. Consulta [categorías de transacciones](categories.md) para conflictos de nombres, permisos y contrato API.

## Comprobantes de movimientos

Los miembros vigentes pueden consultar y descargar adjuntos de movimientos de la cuenta. Solo el autor del movimiento puede subir, reintentar y eliminar sus comprobantes; el propietario no obtiene permiso de gestión sobre archivos ajenos. El acceso sigue a la cuenta actual de la transacción y se comprueba en cada petición. Los adjuntos no modifican ledger, custodia ni reembolsos. Consulta [comprobantes de transacciones](transaction-attachments.md).
