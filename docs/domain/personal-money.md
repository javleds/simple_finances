# Mi dinero disponible

Referencia funcional y técnica para agentes de IA. Describe el comportamiento implementado; consultar también [cuentas compartidas](shared-accounts.md) y [modelo compartido](../shared-account-model.md) antes de modificar transacciones o reembolsos.

## Propósito y límites

Una persona organiza sus gastos en cuentas separadas —casa, hijo, mascota— y necesita conocer cuánto dinero personal le queda. «Mi dinero disponible» reúne sus efectos de efectivo sin duplicar transacciones entre cuentas.

El disponible es un cálculo privado por usuario, independiente del balance contable de cada cuenta y de la custodia compartida. Elegir un origen personal o externo no transfiere dinero, no crea otra transacción y no modifica repartos ni deudas. «Mis gastos» puede ser la cuenta personal elegida; su balance no tiene por qué coincidir con el disponible consolidado.

La cuenta personal debe pertenecer al usuario, tenerlo como único miembro, estar activa y no ser virtual ni tarjeta de crédito. Si deja de cumplir estas condiciones, se suspende el cálculo: `available_balance` es `null`, no cero. Deshabilitar conserva configuración y vínculos.

## Inicio y saldo actual

- Una activación nueva usa `start_mode: now`, un importe libre actual y una `operation_key` UUID. El importe excluye dinero que la persona ya apartó en otras cuentas.
- Activar con $10,000 comienza exactamente con $10,000. Ningún movimiento previamente registrado exige revisión ni se incorpora por su fecha.
- `activated_at` registra el instante del servidor; `transaction_cutoff_id` y `ledger_cutoff_id` delimitan las fuentes existentes. No sustituir estos cortes por un filtro de fecha: movimientos antiguos pueden registrarse después y varios registros pueden compartir segundo.
- `started_at` conserva el día local de referencia para distinguir movimientos anteriores. En modo `now`, la API admite ese día dentro de ayer/hoy/mañana del servidor; si se omite utiliza el día del servidor.
- «Establecer saldo actual» crea un nuevo corte voluntario. Editar sin ese modo, deshabilitar o reactivar conserva el corte existente.
- Un reintento con la misma clave y el mismo payload no vuelve a establecer el corte. Reutilizar la clave con otro payload produce validación fallida. Un reintento antiguo tampoco deshace un reinicio posterior.
- Configuraciones anteriores sin `activated_at` mantienen el modo por fecha: incluyen movimientos desde el inicio de `started_at`. No migrarlas silenciosamente. Pueden adoptar el nuevo comportamiento con «Establecer saldo actual»; después no pueden regresar a `start_mode: date`.

## Fuentes, signos y decisiones

`disponible = opening_balance + suma(effect)`

Solo `automatic` y `personal` aplican `raw_effect`; `external` y `pending` tienen efecto cero. El pendiente se muestra para decidirlo, sin asumir que ya salió o entró dinero.

| Fuente | Efecto cuando se incluye | Comportamiento |
| --- | --- | --- |
| Ingreso en la cuenta personal elegida | Positivo | Automático, salvo movimiento anterior al día inicial registrado después de activar. |
| Egreso en la cuenta personal elegida | Negativo | Automático, con la misma excepción histórica. |
| Ingreso/aportación en una cuenta compartida | Negativo | Representa apartar dinero personal. En un formulario de creación elegible se preselecciona «Mi dinero disponible»; se puede cambiar. |
| Egreso compartido pagado de bolsillo por el usuario | Negativo por el importe completo pagado | Si lo registra el propio pagador, se vincula por defecto; admite una decisión explícita diferente. |
| Egreso registrado por otra persona, con el usuario como pagador de bolsillo | Negativo si se acepta | Queda pendiente para el pagador. El autor no determina de quién es el efectivo. |
| Egreso pagado con fondos de la cuenta o de bolsillo por otro miembro | Sin fuente de egreso personal para este usuario | Preservar custodia, repartos y reembolsos del dominio compartido. |
| Reembolso efectivo recibido o enviado | Positivo al recibir; negativo al enviar según la captura | Requiere clasificación privada; no inferir el origen personal a partir de una liquidación. |

Un ingreso compartido **no es salario** para este cálculo. Ejemplo: disponible $10,000; aportación personal de $1,000 a la cuenta del hijo → $9,000. Una compra posterior de $300 con fondos de esa cuenta conserva $9,000: ya se descontó la aportación. Si la aportación provino de otra persona o de dinero ya apartado, seleccionar «Origen externo» evita descontarla del disponible.

El selector «Origen de tu dinero» usa `personal` («Mi dinero disponible»), `external` («Origen externo») y `pending` («Revisar después»). Solo aparece cuando el cálculo está habilitado, disponible y la fuente es elegible. Los valores por defecto del formulario se aplican al crear; editar no debe reemplazar una decisión existente sin elección explícita. Los ingresos personales ordinarios son automáticos y no muestran este selector.

## Movimientos anteriores y futuros

Con inicio por activación, un movimiento creado después del corte pero fechado antes de `started_at` requiere decidir en el formulario:

- «Ya está incluido en mi saldo inicial» → `external`, efecto cero.
- «Aplicar a mi disponible» → `personal`, efecto con el signo de la fuente.

La UI no preselecciona una opción y bloquea el envío hasta elegir. Si un consumidor API omite la elección, la fuente queda pendiente. Esto también aplica a ingresos y egresos históricos de la cuenta personal.

Solo participan transacciones completadas y cuya fecha no es futura. La UI oculta el selector para fechas futuras; no enviar un origen privado forzado para una fuente que aún no es elegible. Se excluyen transacciones técnicas hijas, migraciones marcadas con `legacy_migrated_at` y registros de cortes de saldo (`account_balance_snapshot_id`).

## Integridad, privacidad y reembolsos

- La clasificación pertenece al usuario autenticado. No exponer saldos privados ni resoluciones en respuestas compartidas, correos o vistas de otros miembros.
- Una aportación completa no puede tener un vínculo `personal` vigente para dos usuarios. Para aportaciones de distintas personas, registrar movimientos separados.
- `source_signature` identifica cuenta, tipo, pagador, fuente de pago y reglas compartidas. Cambiar ese contexto invalida vínculos incompatibles; volver al contexto anterior no debe resucitar decisiones retiradas.
- Editar importe, concepto o fecha sincroniza los vínculos aplicables. Preservar el signo positivo de ingresos personales históricos incluidos. Eliminar una transacción retira su efecto transaccional.
- Reembolsos efectivos se conservan en `personal_money_reimbursements`, identificados por `ledger_entry_key`. Editar o eliminar el gasto original no deshace dinero ya reembolsado.
- La captura diferencia transferencias entre usuarios, devoluciones desde custodia y efectos internos. No convertir todas las filas del libro en movimientos personales ni contar dos veces dinero ya apartado.
- El corte inicial de reembolsos considera tanto IDs existentes del libro como claves de capturas persistidas: borrar/regenerar filas del libro no debe hacer reaparecer reembolsos anteriores a la activación.
- Al salir de una cuenta compartida o archivarla, los vínculos privados ya guardados pueden conservar una instantánea histórica. Esto no concede acceso al detalle compartido actual.
- Mantener intacto `account_member_ledger_entries` y sus reglas de custodia, liquidación y deuda. Una clasificación privada nunca liquida un reembolso compartido.

## Mapa de implementación

| Responsabilidad | Ubicación |
| --- | --- |
| Elegibilidad de cuenta personal | `app/Services/PersonalMoney/EligiblePersonalMoneyAccounts.php` |
| Configuración, cortes e idempotencia | `app/Services/PersonalMoney/UpdatePersonalMoneySettings.php` |
| Selección de fuentes, firmas y efectos | `app/Services/PersonalMoney/BuildPersonalMoneySources.php` |
| Disponible, pendientes y paginación | `app/Services/PersonalMoney/BuildPersonalMoneySummary.php` |
| Clasificación privada y autorización de fuente | `app/Services/PersonalMoney/ResolvePersonalMoneySource.php` |
| Default de egreso propio y elección explícita en escritura | `app/Services/PersonalMoney/ApplyPersonalMoneyTransactionSource.php` |
| Sincronización al editar | `app/Services/PersonalMoney/SyncPersonalMoneyTransactionLinks.php` |
| Capturas de reembolsos y compatibilidad | `app/Services/PersonalMoney/CapturePersonalMoneyReimbursements.php`, `CaptureExistingPersonalMoneyReimbursements.php` |
| Orquestación de escritura | `app/Services/Transaction/TransactionCreator.php`, `TransactionUpdater.php` |
| Facility, configuración y ayuda | `resources/js/modules/personal-money/` |
| Selector y defaults de creación | `resources/js/modules/transactions/components/TransactionsForm.vue` |

Persistencia: `personal_money_settings` guarda la configuración; `personal_money_links`, decisiones e instantáneas privadas; `personal_money_reimbursements`, capturas de efectivo; `personal_money_baseline_operations`, operaciones idempotentes. No usar estas tablas como sustituto del libro compartido.

API autenticada:

- `GET /api/personal-money`: resumen privado, cuentas elegibles e historial; admite `page`, `per_page` (máximo 100) y `resolution: all|pending`. Disponible y cantidad de pendientes se calculan sobre todas las fuentes, independientemente de la página o filtro del historial.
- `PUT /api/personal-money/settings`: `account_id`, `opening_balance`, `enabled`, `started_at` y, al establecer saldo actual, `start_mode: now` y `operation_key`. No reenviar indiscriminadamente el objeto de respuesta: `start_mode: now` representa una acción de reinicio al escribir.
- `PUT /api/personal-money/links`: `source_type: transaction|reimbursement`, `source_id`, `resolution: personal|external|pending`.
- Crear/editar transacciones admite `cash_source`; Vue lo mapea desde `cashSource`. Una fuente personal automática no se resuelve manualmente.

El backend aplica por defecto solo egresos propios elegibles. El default de ingresos compartidos es una selección de UI enviada explícitamente; una integración API que omita `cash_source` no debe asumir ese default.

La caché TanStack Query usa el prefijo `['personal-money']`. Invalidarla al cambiar configuración, decisiones, transacciones o transferencias/reembolsos; conservar también las invalidaciones de cuentas, dashboard y transacciones compartidas. El inicio presenta un resumen compacto con acceso a la facility `/admin/personal-money`.

## Verificación al modificar

Consultar `tests/Feature/Services/PersonalMoney/PersonalMoneyTest.php`, `PersonalMoneyActivationTest.php` y `tests/Feature/Api/PersonalMoneyEndpointTest.php`. En Vue, consultar los specs de `TransactionsForm`, `PersonalMoneySettingsModal`, `PersonalMoneyPage` y `usePersonalMoney`.

Cubrir especialmente: activación exacta con historial previo, cortes en el mismo segundo, reintentos y reinicios, historial registrado tarde, defaults frente a edición, pagador distinto del autor, signos de aportaciones e ingresos, reembolsos persistidos después de regenerar el libro, ausencia de doble conteo, pérdida de elegibilidad y aislamiento entre usuarios. Ejecutar las comprobaciones indicadas en `AGENTS.md` según el alcance del cambio.
