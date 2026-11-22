# Aritmética monetaria exacta con Brick Money

Estado: implementación local completada; activación de datos existentes pendiente de auditoría de cada instalación. Fecha: 2026-11-22.

## Contexto

Finance almacena la mayoría de importes en columnas `DECIMAL`, pero los convierte a flotantes en modelos, DTOs, servicios y contratos. La SPA convierte entradas y respuestas a `number`; MCP entrega cadenas obtenidas parcialmente desde flotantes. Cambiar únicamente los casts o introducir una biblioteca en el creador de transacciones no elimina el problema.

Decisiones confirmadas por el usuario:

- Brick Money para dinero, persistencia decimal exacta y cadenas en los contratos.
- MXN con dos decimales; conservar reglas actuales de reparto.
- No añadir multimoneda ni recalcular deudas históricas.
- MySQL para operación financiera. SQLite queda para pruebas que no dependan de almacenamiento o aritmética monetaria SQL.

El estudio inicial se realizó sobre código, migraciones, documentación y configuración local. No se inspeccionaron datos ni esquema efectivos de producción. La implementación y sus pruebas locales se registran al final del documento. Los tamaños de tablas, valores máximos reales y posibles divergencias del esquema deben medirse en una copia local antes de migrar.

Referencias funcionales: `docs/architecture/boundaries.md`, `docs/shared-account-model.md`, `docs/domain/shared-accounts.md`, `docs/domain/personal-money.md` y `docs/operations/deployment.md`.

## Objetivo

Eliminar la aritmética binaria flotante del recorrido monetario, desde captura hasta persistencia, lectura y transporte, preservando permisos, cortes, signos, orden de reparto y obligaciones existentes. La transición debe ser verificable por centavo y reversible durante una ventana controlada.

## Alcance

- Importes, balances, crédito, custodia, asignaciones, liquidaciones, reembolsos, snapshots y disponible personal.
- Metas, suscripciones, proyecciones, ingresos/egresos fijos, dashboard y calculadora de tarjetas.
- Porcentajes que intervienen en cálculos monetarios, con representación decimal exacta.
- Modelos y consultas de `finance-domain`; Actions, DTOs, validación y serialización de Finance.
- Vue, MCP, extracción OpenAI, Telegram, notificaciones y CSV.
- Migración de las columnas monetarias aproximadas, compatibilidad de idempotencia y pruebas MySQL.

## Fuera de alcance

Multimoneda, tipo de cambio, nuevos productos de crédito, cambio de reglas de reparto, reparación automática de historia, conversión global a centavos, un motor decimal propio, reestructuración general de controladores o eventos y despliegue remoto. Los contadores, IDs, coordenadas de gráficos y métricas no monetarias no se convierten indiscriminadamente a cadenas.

## Comportamiento esperado

1. Un importe capturado como `"123.45"` conserva su valor exacto durante todo el recorrido.
2. Sumas, restas, comparaciones y compensaciones monetarias utilizan Money o aritmética decimal exacta. No pasan por `float`, `Number`, `parseFloat`, `round(float * 100)` ni operadores nativos sobre cadenas decimales.
3. API y MCP devuelven importes con dos decimales, sin separadores de miles ni notación exponencial: `"0.00"`, `"123.45"`, `"-10.00"`. Los valores ausentes conservan `null` cuando corresponda.
4. Las entradas operativas admiten cadenas decimales con cero, uno o dos decimales y se canonizan a dos; rechazan exceso de precisión en lugar de truncarlo o redondearlo silenciosamente.
5. Los repartos conservan participantes, orden y regla de remanente existentes; el total se conserva exactamente. Las anomalías históricas se reportan y no se corrigen como efecto de esta migración.
6. El saldo personal no disponible sigue siendo `null`; no se convierte en `"0.00"`. Una activación repetida no cambia cortes ni saldo inicial.
7. La presentación sigue siendo mobile-first y mantiene componentes, etiquetas, invalidaciones y permisos actuales.

## Decisiones técnicas

### Tipos y límites

- Usar `Brick\Money\Money` directamente en cálculos y DTOs de dominio monetario. Añadir una fábrica pequeña `FinSi\FinanceDomain\Money\CreateMoney` para fijar MXN, escala dos y entradas estrictas, sin recrear la API aritmética de Brick.
- Eloquent mantiene atributos decimales canónicos como cadenas mediante casts `decimal:2`; no serializar objetos Money automáticamente. Convertir a Money al entrar en un cálculo y a cadena al persistir o construir un DTO de respuesta. Probar casts y lectura del driver para impedir conversiones previas a flotante.
- DTOs de entrada validada y de salida usan cadenas; DTOs internos de cálculo pueden contener Money. Documentar esta distinción en nombres y tipos, evitando uniones `float|string|Money` como contrato permanente.
- Los porcentajes financieros usan `BigDecimal` o puntos básicos enteros acotados a 0–10000. Contratos de porcentaje financiero: cadenas con dos decimales. Las posiciones del slider son números visuales; se cuantizan a puntos básicos antes de calcular o enviar.
- Las sumas SQL sobre DECIMAL siguen en MySQL: conservar índices, filtros y agregación en servidor; obtener resultado decimal como cadena. No cargar todo el ledger en PHP para evitar `SUM`. Revisar `CASE`, `COALESCE`, parámetros y literales para no inducir tipos aproximados.
- Los rangos siguen limitados por las columnas actuales. No ampliar todas las precisiones como parte del cambio. Validar tanto entradas como resultados que se persistirán; una biblioteca de precisión arbitraria no amplía el rango SQL.
- Limitar longitud y escala antes de invocar Brick o una biblioteca JS, evitando entradas con millones de dígitos. Derivar el límite de cada campo del esquema y contrato.

### Dependencias

Declarar `brick/money` y `brick/math` explícitamente en `packages/finance-domain/composer.json` si ambos se importan directamente. Finance fija actualmente Math 0.14.6 y MCP 0.14.8; no asumir compatibilidad automática con Money actual. Resolver una versión estable compatible con PHP 8.2 y Laravel 12 mediante Composer, revisar cambios transitivos y fijar el ciclo compatible. Actualizar los locks de los consumidores sin actualizar dependencias ajenas. No usar `master` como dependencia.

Brick puede funcionar sin GMP/BCMath. Medir con datos representativos antes de exigir una extensión; si se incorpora para rendimiento, alinear host, imágenes y CI.

Para Vue, proponer `big.js` y sus tipos compatibles: ya hay divisiones, proyecciones e intereses que justifican una biblioteca decimal frente a desarrollar un motor con BigInt. Encapsular únicamente parsing, formato y operaciones compartidas necesarias, con configuración local y entradas string estrictas. La autoridad contable permanece en Laravel. La versión exacta y compatibilidad con TypeScript 6 se fijan al instalar y comprobar el lock.

Las divisiones periódicas de big.js no son exactas por elegir muchos decimales. Para asignaciones a centavos, representar la fórmula como numerador/denominador enteros, obtener cociente y resto mediante la biblioteca y decidir el redondeo comparando el resto; evitar una aproximación intermedia seguida de otro redondeo. Para simulaciones que requieran aproximación, usar una instancia local con DP=40 y modo explícito equivalente a mitad alejándose de cero, documentar los puntos de redondeo por periodo y comprobar fixtures próximos a mitades de centavo contra el cálculo racional backend. No usar resultados aproximados de simulación para autorizar o persistir movimientos.

Fuentes técnicas consultadas: [Brick Money](https://github.com/brick/money), [dependencias de Brick Money](https://github.com/brick/money/blob/master/composer.json), [Brick Math](https://github.com/brick/math), [big.js](https://github.com/MikeMcl/big.js), [DECIMAL de MySQL](https://dev.mysql.com/doc/refman/8.4/en/fixed-point-types.html) y [tipos SQLite](https://www.sqlite.org/datatype3.html). Las referencias a ramas de desarrollo son informativas; implementar contra la documentación de la versión fijada.

### Redondeo y asignación

- Entrada/persistencia de importes contabilizados: exigir representabilidad exacta a dos decimales; no redondear automáticamente una captura con tres decimales.
- Donde el código aplica `round(..., 2)` sin modo, caracterizar y reproducir su intención de mitad alejándose de cero mediante el modo equivalente de la versión de Brick elegida. No imponer HALF_EVEN globalmente.
- `BuildSplitTransactionAllocations` redondea cada parte salvo la última y entrega a esta el remanente. `ResolveOutcomeUserPayments` ordena defaults por ID; un reparto explícito conserva el orden enviado. No sustituir por `Money::allocate()` sin demostrar equivalencia.
- Preservar también el orden de consumo de custodia, créditos antiguos, pagos parciales y ajustes del último item de reembolso.
- Usar fracciones/racionales para cadenas de multiplicación y división que no tengan puntos de redondeo intermedios definidos; conservar los puntos intermedios que ya sean reglas del producto.
- Probar repartos pequeños entre muchos participantes: `0.03` con porcentajes `[16.67, 16.67, 16.67, 16.67, 16.67, 16.65]` produce cinco asignaciones de `0.01` y un remanente de `-0.02` con la regla actual. Si aparece un remanente negativo, bloquear la nueva operación con validación explícita y registrar el caso como decisión de producto pendiente; no inventar un reparto distinto ni reparar filas anteriores. Una edición histórica que requiera regenerarlo necesita resolución explícita antes de desplegar ese caso.
- Reemplazar tolerancias como ±0.001 por comparaciones exactas con cero solo en valores ya canonizados a centavos. No aplicar esta sustitución a tasas o resultados intermedios.

## Componentes afectados

| Área | Recorrido o componentes existentes | Cambio requerido |
| --- | --- | --- |
| Transacciones | `TransactionRequest` → `TransactionFormDto::fromFormArray` → `TransactionCreator/Updater/Remover` | Validación exacta, DTOs y escritura sin float |
| Repartos y libro | `BuildSplitTransactionAllocations`, `SyncTransactionAllocations`, `SyncAccountMemberLedger`, `SyncCoveredPayerRecoveryTransaction` | Aritmética exacta preservando regeneración y reglas históricas |
| Balance/crédito | `RecalculateAccountBalance`, métodos de `Account`, `AccountBalanceSnapshotDto`, `AccountBalanceMetaDto` | SUM exacto, signos y corte de tarjeta sin cambios funcionales |
| Custodios y pagos | `RegisterAccountMemberTransfer`, `CreateAccountCustodian`, `RetireAccountCustodian`, `ReopenVirtualCustodians` | Conservación, disponibilidad y pagos parciales exactos |
| Lecturas | `BuildAccountMemberSummary`, `BuildPendingReimbursementItems`, `BuildAccountLedgerTimeline`, Dashboard | Totales, comparación con cero, badges, orden y contratos |
| Dinero privado | `BuildPersonalMoneySources/Summary`, `UpdatePersonalMoneySettings`, `SyncPersonalMoneyTransactionLinks`, capturas de reembolso | Signos, cortes, hash y decisiones privadas |
| Otros importes | `CaptureVirtualAccountBalanceSnapshot`, `RecalculateFinancialGoalProgress`, servicios Subscriptions | Deltas, proyecciones, ahorros, formato y límites |
| API | `ApiController::respondModel/respondPaginated`, controladores concretos, DTOs de metadata | Modelos, relaciones, pivots, arrays y metadata con cadenas |
| Canales | `OpenAIService`, prompts, DTOs de extracción/modificación, Telegram, `helpers.php::as_money`, resúmenes semanales | Captura, comparación, correo y CSV exactos |
| Consumidores | Vue, `FinancialReadTools`, `FinanceTool`, cliente MCP Python | Cadenas hasta presentación y checkpoints |

## Backend

Migrar flujos completos; no activar globalmente los casts nuevos mientras quedan servicios que reciben floats. Añadir primitives y pruebas primero, integrar grupos relacionados en una rama y liberar el conjunto compatible.

Conservar los filtros actuales de `RecalculateAccountBalance`: ingresos legacy/hijos y egresos tienen criterios diferentes. Conservar las banderas `uses_shared_account_rules`, `uses_virtual_custodian_rules`, `legacy_migrated_at` y movimientos técnicos. No simplificar el libro como tabla cruda.

`SyncPersonalMoneyTransactionLinks` concatena importes redondeados en un `CASE` SQL. Sustituir por bindings de cadenas decimales manteniendo autorización y firma de contexto.

`ProcessTransactionSideEffects` recalcula balance/metas después de la transacción de creación. Brick no soluciona esta frontera de concurrencia preexistente. Medir y probar el flujo; validar resultados que podrían desbordar antes de confirmar escrituras. Si no se puede garantizar ausencia de escrituras parciales ante un error nuevo de precisión/rango, incorporar el cambio mínimo de frontera transaccional de los efectos contables, manteniendo notificaciones externas después del commit. Cualquier ajuste debe tener prueba de rollback y concurrencia; no refactorizar la orquestación completa.

### Idempotencia del saldo inicial

`UpdatePersonalMoneySettings` calcula `payload_hash` con un array JSON que incluye `openingBalance` float. Cambiarlo a cadena cambia el hash. La tabla de operaciones solo guarda el hash, no el payload histórico: no es posible recalcular todos los hashes desde el saldo actual.

- Añadir `payload_hash_version` mediante migración: filas existentes versión 1, nuevas escrituras versión 2.
- Versión 2: canonizar importe y tipos antes de calcular el hash. `"100"` y `"100.00"` representan la misma operación; un centavo distinto no.
- Versión 1: comparador de compatibilidad aislado que reproduce la serialización antigua únicamente para verificar identidad de un replay; nunca alimenta cálculos o persistencia monetaria. Exigir que el importe sea representable con ida y vuelta exacta a centavos dentro del rango legado antes de realizar esta compatibilidad. Probar configuración histórica de serialización y rechazar de forma explícita casos no verificables, sin reactivar ni borrar la operación.
- Cubrir cero negativo: la serialización histórica puede contener `-0`, aunque la entrada nueva sea `"0.00"`. Para importe exactamente cero, ensayar únicamente las variantes históricas positivas/negativas de cero como equivalentes; no extender esa tolerancia a otros importes ni cambiar el hash almacenado.
- Esta excepción histórica puede necesitar una conversión float exclusivamente para reconstruir el hash antiguo. Mantenerla identificada y probada; no prometer cero apariciones textuales de `float` en todo el repositorio.
- No sobrescribir hashes antiguos ni reconstruirlos desde la configuración vigente. No cambiar `source_signature`, que identifica contexto y no incluye importe.

## Frontend

Reutilizar `AppInput`, `AppPercentageSplitEditor`, `AppModal`, `AppHeroMetric`, `AppAvatarValueRow` y las páginas/repositorios actuales. No introducir otra biblioteca de formularios ni cambiar el diseño.

- Crear tipos compartidos `MoneyString` y `PercentageString` validados, y helpers en `modules/shared/lib/money.ts`; no confiar en un simple alias como validación runtime.
- `apiParsing.ts::parseNullableNumber` permanece para números no monetarios. Introducir parsing decimal estricto y migrar los mapeadores monetarios de transactions, accounts, subscriptions, virtual-accounts y personal-money.
- `AppInput` ya emite cadenas, pero convierte la parte entera con `Number`, elimina negativos y trunca precisión. Adaptar el modo monetario para conservar dígitos, admitir signo solo donde proceda y dejar errores de precisión visibles. Conservar estados de edición incompletos sin enviarlos.
- `AppPercentageSplitEditor` recibe/emite cadenas en modo monetario y usa cálculo exacto. El slider conserva geometría numérica; sus resultados se normalizan antes del dominio. Porcentajes financieros se mapean sin flotantes.
- Centralizar formato monetario exacto; no usar `Number(amount)` como paso para `Intl.NumberFormat`. Mantener el estilo español/MXN actual, signos, cero y `null` con pruebas.
- Revisar sumas, restas, comparaciones, ordenamientos, `.toFixed`, truthiness de `"0.00"`, límites, badges y valores por defecto.
- Charts pueden proyectar a number solo para geometría, con valores finitos y rango controlado; etiquetas/tooltips y totales usan las cadenas originales. Una proyección fuera de rango muestra un estado explícito, no un total falso.
- Migrar también `CreditCardPayoffPage.vue`, `DashboardSubscriptionsPlanning.vue` y simulaciones del módulo distribution. Las simulaciones tienen política de precisión/redondeo documentada y fixtures compartidos con las reglas backend cuando corresponda.
- Preservar invalidaciones de cuentas, dashboard, transactions y personal-money tras escrituras.

## Persistencia

### Inventario inicial basado en migraciones

| Tabla/campo | Tipo declarado actual | Tratamiento previsto |
| --- | --- | --- |
| `accounts.balance`, `credit_line`, `available_credit` | DECIMAL(10,2); available_credit proviene de scoped_balance | Conservar tipo/rango/nullability |
| `transactions.amount`, `subscription_payments.amount`, `financial_goals.amount/achieved_amount`, `partial_fixed_incomes.amount`, `fixed_outcomes.amount` | DECIMAL(10,2) | Conservar valores; aritmética y validación exactas |
| `subscriptions.amount` | DECIMAL con defaults de Laravel | Verificar tipo real; conservar rango |
| `transaction_allocations.amount`, `account_member_ledger_entries.amount` | DECIMAL(12,2) | No regenerar filas por migración |
| `account_balance_snapshots.observed_balance/previous_balance/delta` | DECIMAL(12,2) | Conservar snapshots |
| `personal_money_settings.opening_balance`, `personal_money_links.cash_effect`, `personal_money_reimbursements.cash_effect` | DECIMAL(15,2) | Conservar datos, signos y cortes |
| Porcentajes de invitaciones, miembros, transacciones y allocations | DECIMAL(5,2) | Conservar persistencia; contratos/cálculo exactos |
| `accounts.spent` | DOUBLE nullable | Nueva representación DECIMAL(19,2) nullable; rango operativo compatible con crédito actual |
| `shared_transaction_notification_items.amount` | FLOAT | Nueva representación DECIMAL(19,2), conservando obligatoriedad |
| `financial_goals.progress` | FLOAT | Métrica visual: puede seguir numérica; no usar para derivar dinero |
| `personal_money_baseline_operations.payload_hash` | Hash sin versión | Añadir versión de hash, sin reescribir hashes existentes |

DECIMAL(19,2) es la propuesta para las dos columnas aproximadas, no una afirmación de que todos sus valores históricos caben. La auditoría de rango y conversión es un requisito de avance. No reducir, saturar ni truncar filas incompatibles.

### Conversión y conciliación

1. Obtener una copia local autorizada y protegida; no incorporar datos, dumps o reportes privados al repositorio. Registrar versión MySQL, esquema, cantidad de filas, nulos, mínimos/máximos y precisión efectiva.
2. Crear columnas sombra decimales para `spent` y el importe de notificaciones, inicialmente nullable para distinguir pendientes. Generar migraciones con `php artisan make:migration`; no editar migraciones históricas.
3. Preparar una acción/comando local por lotes, idempotente y reanudable por clave primaria, que lea la representación textual SQL de los valores aproximados y convierta con política decimal explícita. Documentar la expresión MySQL de extracción y probarla contra esa versión. No ejecutar `(float) * 100`.
4. Reportar todo valor con más de dos decimales, fuera de rango o ambiguo. Comparar con la transacción fuente cuando exista sin asumir que el importe actual reconstruye el de una notificación histórica. Los errores antiguos de FLOAT no son recuperables por instalar Money.
5. Registrar decisiones de conversión; solo aplicar la conversión propuesta y revisada. Comparar filas, nulos, sumas, diferencias por fila y por cuenta; los agregados solos pueden ocultar diferencias compensadas.
6. En el corte de mantenimiento, detener todos los escritores y completar/verificar el backfill sobre el estado final. En despliegue con volumen grande se requiere captura de cambios o bloqueo de escritores durante toda la carga: no dejar una sombra desactualizada.
7. Cambiar el mapeo a columnas exactas conservando nombres de atributos y contrato público. Una opción concreta es renombrar las originales a columnas legacy y las sombras al nombre original; ensayar DDL y locks con MySQL. Las migraciones DDL no se consideran reversibles por una transacción PHP.
8. Conservar las columnas legacy durante la ventana de reversión; retirarlas en una entrega posterior, con migración independiente.

No regenerar ledger, repartos, capturas privadas, snapshots o balances históricos durante el backfill. La comparación de cálculo nuevo contra datos persistidos es diagnóstica: toda discrepancia bloqueante se clasifica; no se aplica reparación implícita.

### MySQL y SQLite

Crear perfiles de prueba MySQL aislados para Finance, paquete y MCP. `packages/finance-domain/tests/TestCase.php` fuerza SQLite en `defineEnvironment`; cambiar solo variables en phpunit.xml no basta. Las pruebas monetarias deben crear DECIMAL con precisión representativa del esquema real. SQLite puede conservarse para pruebas unitarias, arquitectura o permisos sin afirmaciones de exactitud SQL. No certificar la migración por aprobar únicamente SQLite.

## API

Mantener rutas, envelopes, IDs, paginación y errores Laravel. Cambia el tipo de importes y porcentajes financieros, no su nombre ni significado.

| Campo/ejemplo | Antes | Después |
| --- | --- | --- |
| `amount`, `balance`, `custody_amount`, `cash_effect` | JSON number | `"123.45"`, según signo permitido |
| `available_balance` no elegible | null | null |
| `percentage` financiero | JSON number | `"33.33"` |
| `id`, `count`, paginación | JSON number | JSON number |
| `progress` visual, coordenadas | JSON number | JSON number; sin reutilización contable |

Cubrir modelos directos de `respondModel`, paginadores, relaciones, pivots, DTOs de metadata y resúmenes hechos con arrays. No convertir recursivamente cualquier clave llamada `amount` ni todo número a string: cada contrato declara sus campos.

### Compatibilidad y corte

El contrato final exige cadenas en entradas monetarias y no vuelve a convertir respuestas a números para clientes antiguos. No prometer exactitud de números JSON ya decodificados como float.

Preparar un corte coordinado de SPA/backend/consumidores internos, aprovechando mantenimiento existente. Proponer una revisión de contrato explícita, por ejemplo `X-Finance-Contract: 2`, enviada por el cliente HTTP nuevo y comprobada en rutas financieras afectadas. Clientes sin revisión válida reciben conflicto controlado con código de actualización requerida, sin escrituras. Incluir una prueba de pestaña antigua y recarga. Mantener 401/403 conforme a autenticación/autorización; no bloquear rutas públicas, descargas firmadas o adjuntos ajenos al contrato.

Inventariar consumidores externos antes del corte. Si existen clientes no actualizables, añadir una fase de compatibilidad/versionado explícito antes de activar el contrato estricto; no asumir que no existen. No añadir aceptación permanente de `number|string` al núcleo. No desplegar commits intermedios con contratos mezclados.

MCP ya devuelve cadenas: conservar su forma y cambiar cálculo interno. Python debe preservar cadenas en resultados, mensajes y checkpoints; no requiere migración PostgreSQL por este cambio. Si necesita calcular, usar Decimal desde texto.

## Validaciones

- Validar tipo string, gramática decimal, longitud, escala, signo y rango antes de crear Money. Rechazar exponente, separadores de miles, NaN/Infinity, cadena vacía y números JSON.
- Normalizar ceros iniciales y cero negativo; ausencia no equivale a cero. La interfaz local puede aceptar separador decimal local mediante normalización explícita previa al contrato.
- Importes de transacción positivos; balances/efectos admiten negativos según campo; cero permitido solo donde hoy corresponda.
- Porcentajes entre 0 y 100, máximo dos decimales, suma exacta 100 cuando se requiera reparto. No normalizar automáticamente un total inválido.
- Rechazar división por cero y resultados no representables con errores de dominio/validación, antes de persistir parcialmente. Conservar el formato de errores por campo y 422.
- OpenAI: modificar prompts/esquemas para cadenas y validar realmente la respuesta. Un prompt no sustituye la validación. Entradas ambiguas o con precisión inválida no generan movimientos.

## Permisos y seguridad

Conservar actor explícito, consultas de visibilidad, autoría de edición/eliminación, privacidad de personal-money y separación de custodios virtuales frente a miembros. MCP sigue siendo solo lectura y el modelo no elige identidad. Las pruebas MySQL usan bases desechables y credenciales separadas, con protección contra apuntar a una base operativa. No enviar correos, ejecutar herramientas externas ni mutar filas durante la comparación de motores.

## Casos límite

Cero y cero negativo; un centavo; negativos; máximos de cada DECIMAL; sumas de muchas filas que exceden el rango de una columna; mitades positivas/negativas; división periódica; repartos de 100 entre tres y de 0.01 entre muchos; porcentajes con dos decimales; valores legacy aproximados; usuario pagador distinto de autor; custodios reales/virtuales retirados; fondos insuficientes; cambios históricos de cuenta/fuente; reembolso parcial y crédito antiguo; saldo personal null; reintentos de activación antes/después de un reinicio; pestaña antigua; colas previas al cambio; formatos de CSV y JSON; herramientas MCP y gráficos con cantidades grandes.

## Estrategia de pruebas

### Caracterización y exactitud

Crear fixtures deterministas sin datos personales con entrada, orden, redondeo y resultado esperado en cadenas. Comparar el resultado matemático esperado, no copiar un float defectuoso como oráculo. Clasificar diferencias respecto al comportamiento anterior y no asumir que toda diferencia es una corrección autorizada.

Probar propiedades con conjuntos deterministas: conservación de sumas, inversa suma/resta, no creación/destrucción de centavos, invariancia de valor en parsear/persistir/leer/serializar y comparación exacta. Cada nueva Action lleva pruebas de comportamiento.

### Suites existentes que deben ampliarse

- Finance: `tests/Feature/Services/Transaction/{TransactionCreatorTest,TransactionUpdaterTest,TransactionRemoverTest,ValidateAccountFundPaymentTest}.php`.
- Cuentas: `tests/Feature/Services/Accounts`, `tests/Feature/Api/{SharedAccountTransactionLifecycleTest,AccountCustodianTest,VirtualCustodianTransactionsTest,TransactionReimbursementResponseTest,AccountLedgerRepairTest,AccountBalanceSnapshotEndpointsTest}.php`.
- Personal: `PersonalMoneyTest`, `PersonalMoneyActivationTest`, `PersonalMoneyEndpointTest`; replay de hash versión 1 y 2, centavo distinto, reinicio posterior y disponibilidad null.
- Lecturas: suites Dashboard, `CaptureVirtualAccountBalanceSnapshotTest`, suscripciones, notificaciones y migraciones legacy. No eliminar pruebas legacy al introducir el perfil MySQL.
- Paquete: `FinancialReadToolsTest`, `AuthorizationTest`, `VirtualCustodianTest`, `ArchitectureTest` y nuevas pruebas de creación/casts/serialización monetaria.
- MCP: `tests/Feature/FinancialMcpTest.php`, `tests/Integration/MySqlReadOnlyTest.php`; igualdad exacta con el dashboard para el mismo actor y snapshot.
- Vue: schemas de transactions/accounts/virtual-accounts/personal-money, repositorios, `AppInput`, `AppPercentageSplitEditor`, formularios, badges, filtros, cálculos y formateo. Casos de `"0.00"` truthy y ordenamiento lexicográfico incorrecto.
- Agents: `test_graph.py`, `test_api.py`, `test_conversation_context.py`, `test_postgres.py` según cambios; comprobar cadenas en tool outputs/checkpoints sin llamadas reales al modelo.
- Nuevas pruebas de migración: backfill interrumpido/reanudado, nulos, valores fuera de rango, discrepancias de precisión, corte, reversión y escrituras concurrentes bloqueadas.

### Conciliación local

Comparar por cuenta y usuario: balance, custodia, settlements, reembolsos y sus items, efectos personales, snapshots, dashboard y MCP. Ejecutar sobre estado consistente con escritores detenidos o snapshot coherente. La comparación solo lee; no invocar Actions con notificaciones. Exigir cero diferencias sin explicación y decisión registrada, no solamente igualdad del total global. Medir duración/memoria y número de consultas con un volumen representativo.

## Estrategia de validación

Los comandos siguientes describen la estrategia de verificación; las configuraciones efectivas y los resultados locales se registran en la guía operativa y al final de este documento.

| Directorio | Comprobación |
| --- | --- |
| `packages/finance-domain` | `composer test`; perfiles SQLite permitidos y MySQL financiero |
| `apps/finance` y `apps/mcp` | `composer run domain:sync` tras cambiar paquete; antes, resolver dependencias nuevas en los locks de cada consumidor |
| `apps/finance` | `php artisan test` y perfil MySQL explícito con base desechable |
| `apps/mcp` | `composer test` y perfil MySQL/solo lectura |
| `apps/finance` | `composer run format:check`; revisar alcance de la configuración |
| `apps/finance` | `npm run type-check`, `npm run test:unit -- --run`, `npm run build` |
| `apps/finance` | `npx --no-install oxlint resources/js`, `npx --no-install eslint resources/js` sin --fix para verificar |
| `apps/agents` | `uv run pytest`, `uv run ruff check .`, `uv run ruff format --check .`, `uv run mypy src` |
| Raíz | `git diff --check`; búsqueda residual contextual de floats/casts/round/sum y revisión de fronteras monetarias |

Crear configuraciones `phpunit.mysql.xml` y documentar sus comandos exactos al implementarlas, incluyendo preparación y limpieza de bases locales. Para integración Python, `AGENT_TEST_DATABASE_URL` debe identificar una base aislada.

`composer.json` declara `stan` y `pint`, pero las dependencias declaradas no aseguran PHPStan/Larastan/Pint; `phpstan.neon.dist` referencia extensiones no declaradas. No reportar esos checks como disponibles o aprobados sin resolver/verificar tooling. No añadir herramientas ajenas silenciosamente. `npm run lint` tiene --fix: no utilizarlo como comprobación de solo lectura.

## Estrategia de commits

Commits convencionales en inglés, revisables, dentro de una rama de implementación. Solo liberar el conjunto cuando contratos y consumidores estén alineados.

1. `test(finance): characterize monetary rules and add mysql test profiles`
2. `feat(domain): add exact money construction and decimal conventions`
3. `feat(finance): prepare exact monetary storage and idempotency versions`
4. `refactor(finance): use exact money in transaction and custody flows`
5. `refactor(finance): preserve exact personal balances and projections`
6. `refactor(domain): calculate financial read models without floats`
7. `feat(api): expose exact decimal contracts and enforce contract revision`
8. `feat(ui): preserve decimal strings across financial workflows`
9. `refactor(integrations): preserve exact amounts in financial channels`
10. `test(finance): verify monetary migration and end-to-end reconciliation`
11. `docs(finance): document exact money contracts and migration procedure`

Ajustar divisiones para que cada commit compile cuando sea posible; no separar un cambio de firma de sus consumidores inmediatos. La migración de eliminación de columnas legacy pertenece a una entrega posterior.

## Archivos estimados a modificar

Las rutas de grupos siguientes son un inventario de alcance, no una orden de editar todos los archivos del directorio.

- `packages/finance-domain/composer.json`, `src/Money/CreateMoney.php` (nuevo), `src/Models/{Account,Transaction,TransactionAllocation,AccountMemberLedgerEntry,AccountBalanceSnapshot,FinancialGoal,Subscription,PersonalMoneySetting,PersonalMoneyLink,PersonalMoneyReimbursement,SharedTransactionNotificationItem}.php` y otros modelos de importes/porcentajes; `src/ReadModels/*` financieros y tests.
- `apps/finance/composer.json`, `composer.lock`, `apps/mcp/composer.lock` y configuración de pruebas de los tres proyectos PHP.
- `apps/finance/app/Dto/{TransactionFormDto,UserPaymentDto,SplitTransactionAllocationDto,AccountBalanceSnapshotDto,AccountBalanceMetaDto,TransactionSummaryDto,PersonalMoneySettingsDto,TransactionExtractionDto,TransactionModificationDto}.php` y DTOs de proyecciones/custodios.
- `apps/finance/app/Http/Requests/Api/*` monetarios, controladores/serialización correspondientes y cliente de revisión de contrato.
- `apps/finance/app/Services/{Transaction,Accounts,PersonalMoney,VirtualAccounts,FinancialGoals,Subscriptions,Dashboard,SharedTransactions,OpenAI,Telegram}` en los recorridos inventariados.
- `apps/finance/helpers.php`, `app/Services/WeeklySummaryProcessor.php`, `AccountSummaryCreator` y plantillas monetarias.
- Nuevas migraciones generadas con Artisan para columnas sombra, hash versionado y corte; acción/comando de auditoría/backfill con pruebas.
- `apps/finance/resources/js/modules/shared/lib/{apiParsing,money}.ts`, tipos monetarios compartidos, `AppInput.vue`, `AppPercentageSplitEditor.vue`, schemas/types/repositorios/componentes de los módulos financieros y calculadora de tarjetas.
- `apps/finance/package.json`, lock npm; `apps/mcp/tests`, pruebas Python consumidoras si cambia el tratamiento de resultados.
- `docs/architecture/boundaries.md`, `docs/domain/personal-money.md`, `docs/shared-account-model.md`, `docs/operations/local-development.md`, `docs/operations/deployment.md` y contratos actuales pertinentes al terminar implementación; no modificar referencias históricas como si fueran vigentes.

## Riesgos

| Riesgo | Mitigación y condición de avance |
| --- | --- |
| Error histórico ya persistido | Auditoría y decisión por diferencia; no afirmar recuperación de precisión perdida |
| Otra asignación del centavo residual | Fixtures por orden; no adoptar defaults de allocate |
| API vieja con frontend nuevo o viceversa | Corte coordinado, revisión explícita y pruebas de pestañas abiertas |
| Reintentos personales cambian de hash | Versionado y compatibilidad legacy verificada sin reiniciar cortes |
| SUM/CASE vuelve a aproximado | Tests sobre MySQL real, resultados string y bindings exactos |
| Error después de confirmar movimiento | Prevalidación de rangos y prueba de atomicidad; ajuste mínimo contable si necesario |
| DDL bloquea o backfill queda obsoleto | Ensayo con volumen real, mantenimiento y escritores detenidos |
| Jobs serializados de una versión anterior | Inventariar payloads, drenar/reiniciar workers; probar reintentos sin duplicar efectos |
| Cálculo exacto más costoso | Mantener agregaciones SQL, medir y evitar nuevos N+1 |
| Testbench fuerza SQLite | Perfil MySQL real en configuración y creación del esquema |
| Retorno a float en formato/exports/UI | Helpers exactos, revisión contextual y pruebas de extremo a extremo |

## Preguntas abiertas

No quedan abiertas las decisiones de moneda, escala o motor operativo: el usuario confirmó MXN, dos decimales y MySQL.

Pendientes verificables antes de activar el cambio:

- Dependencias resueltas en la implementación: Brick Money 0.10.3, Brick Math 0.14.8 y big.js 7.0.1 con sus tipos.
- Esquema efectivo, volumen y valores legacy: se obtienen de copia local, no se infieren del repositorio.
- Existencia de consumidores externos del contrato numérico y jobs antiguos que deban drenarse.
- Resultado de casos de sobreasignación y discrepancias históricas. Si exigen cambiar reglas de negocio, presentar casos concretos antes de corregirlos.
- Configuración histórica de serialización de hashes y casos legacy que no permitan verificar replay de forma segura.

Estos puntos son puertas de verificación del plan, no autorización para adivinar o modificar historia. No impiden comenzar caracterización y primitives.

## Plan de implementación

| Etapa | Propósito y cambios | Dependencias | Validación y resultado exigido |
| --- | --- | --- | --- |
| 1. Caracterizar | Inventario campo→tipo→entrada→cálculo→salida; fixtures de reparto/signos/hash; perfiles MySQL en Finance, paquete y MCP | Ninguna | Tests existentes como baseline, MySQL aislado y discrepancias actuales identificadas |
| 2. Introducir tipos | Resolver dependencias; CreateMoney, convenciones de porcentaje, parsing y serialización exacta sin activar casts globales | 1 | Pruebas de escala, rango, signos, operaciones y JSON; versiones compatibles fijadas |
| 3. Preparar datos | Migraciones Artisan aditivas, hash versión 2, acción de auditoría/backfill y ensayo local | 1–2 | Reanudación, comparación por fila, nulos y rollback previo al corte; sin historia regenerada |
| 4. Migrar núcleo | DTOs, repartos, ledger, custodia, transferencias, recovery, balance y crédito como conjunto coherente | 2; columnas exactas preparadas para su prueba | Ciclo crear/editar/mover/borrar/liquidar, conservación y permisos; rollback ante fallo monetario |
| 5. Migrar dinero privado | Sources/summary/links/capturas, hashes y snapshots; metas, suscripciones, fijos y proyecciones | 4 | Signos y cortes intactos, replay legacy/nuevo, null, proyecciones y privacidad |
| 6. Migrar lecturas/API | Modelos decimal:2, read models, DTOs metadata, pivots, validadores y revisión de contrato; mantener MCP string | 4–5 y pruebas de todos los consumidores PHP | Contratos anidados exactos; ninguna ruta monetaria activa depende de float |
| 7. Migrar SPA | Helpers big.js, tipos/schemas, AppInput, splitter, formato, páginas y calculadora; enviar revisión de contrato | 2 y contrato de 6 | Type-check, Vitest, build, fixtures de centavos, teclado móvil y pestaña antigua |
| 8. Migrar canales | OpenAI/Telegram, correos/CSV, payloads de jobs, MCP y preservación Python | 6 | Tests de captura, formato, reintentos y equivalencia dashboard/MCP sin efectos externos |
| 9. Ensayar corte | Integrar todo sobre copia MySQL; conciliación antes/después; mantenimiento, backfill final, cambio de columnas, reinicio de consumidores y rollback | 3–8 | Cero diferencias sin resolución, rendimiento aceptable y procedimiento reproducible |
| 10. Cerrar implementación | Validaciones completas, revisión de diff/seguridad de datos y actualización de documentación vigente | 9 | Commits locales coherentes, evidencia de pruebas y entrega para revisión; sin ejecutar servidor remoto |

### Secuencia de activación prevista

La implementación local prepara y ensaya esta secuencia; ejecutarla en producción sería otra tarea autorizada por separado.

1. Tener respaldo verificado y artefactos de la versión anterior; comprobar clientes, jobs, esquema y conversiones aprobadas.
2. Activar mantenimiento y detener escritores/consumidores según operación existente; no dejar Telegram u otros workers escribiendo.
3. Completar migraciones aditivas y backfill final; comprobar conciliación antes del corte de columnas.
4. Activar backend, SPA y consumidores compatibles; reiniciar procesos largos para no mezclar código viejo y nuevo.
5. Ejecutar comprobaciones de lectura, contrato y operación controlada; abrir tráfico solo con invariantes verificadas.
6. Conservar columnas legacy y respaldo hasta terminar la ventana de reversión; no eliminar evidencias durante el mismo despliegue.

Antes de reabrir escrituras, la reversión puede restaurar esquema/código/datos anteriores sin perder operaciones nuevas. Después de reabrir, un rollback no consiste en renombrar columnas legacy: ya pueden estar desactualizadas. Detener escritores y reconciliar todas las operaciones nuevas antes de recuperar la versión anterior; si no es seguro, preferir corrección hacia adelante manteniendo datos exactos. No restaurar un respaldo antiguo descartando movimientos recientes.

### Criterio de finalización

Se termina cuando entradas y respuestas monetarias son cadenas canónicas, cálculos/persistencia son exactos, consumidores están alineados, reglas y privacidad se conservan, reintentos históricos siguen siendo seguros, migración y reversión están ensayadas y las pruebas MySQL/frontend/MCP pertinentes pasan. Una búsqueda sin resultados de la palabra float no sustituye esta evidencia.


## Registro de implementación local

Se implementaron las etapas de código y pruebas con Brick Money 0.10.3, Brick Math 0.14.8 y big.js. Los atributos Eloquent, DTOs de transporte y contratos conservan cadenas; el adaptador `Decimal` usa Money para operaciones monetarias y BigDecimal/BigRational para proporciones. No se introdujo una clase numérica propia ni multimoneda.

La activación se realiza mediante `money:storage`: auditoría por lotes, aprobación del checksum, carga/verificación de sombras y corte conservando columnas legacy. `--check` bloquea PostDeploy mientras quede almacenamiento aproximado. Se ensayó con datos sintéticos MySQL, incluyendo la pérdida de precisión de CAST(FLOAT AS CHAR), valores fuera de rango, cambios posteriores a aprobación y reanudación. No se inspeccionaron ni migraron datos reales.

La revisión MySQL requirió ampliar el campo ledger.type de 32 a 64 para admitir valores del enum ya existentes. También se protegieron los bloqueos/refresco de snapshots y movimientos con objetos desactualizados; el conflicto devuelve 409 sin escribir. Los recálculos contables son atómicos y la entrega de notificaciones queda después del commit.

El plan anterior se conserva como especificación de decisiones y criterios. La guía operativa vigente contiene los comandos efectivos y la limitación del rollback después de nuevas escrituras. La conciliación de una copia real y la revisión de consumidores externos siguen siendo requisitos de activación, no una tarea ejecutada desde este checkout.

### Validación ejecutada

- Finance sobre MySQL 8.4: 512 pruebas aprobadas, 2,643 aserciones y una omisión condicionada por Imagick.
- Paquete compartido sobre MySQL: 36 pruebas y 280 aserciones; perfil SQLite unitario: 14 pruebas y 189 aserciones.
- MCP sobre MySQL, incluyendo credencial de solo lectura: 15 pruebas y 70 aserciones.
- SPA: 77 archivos y 342 pruebas aprobadas; type-check, build y ESLint de archivos modificados aprobados.
- Transporte/contexto del agente: 20 pruebas aprobadas; Ruff y mypy aprobados.
- Perfil SQLite unitario de Finance: 54 pruebas y 133 aserciones. Excluye expresamente la prueba de notificación posterior al commit, que requiere MySQL.
- Oxlint global conserva 96 errores preexistentes, comparados con HEAD; se corrigieron los tres errores introducidos por este cambio. No se deshabilitaron reglas.

Estas verificaciones usan datos sintéticos y no sustituyen la conciliación ni la medición con el volumen real antes de activar producción.
