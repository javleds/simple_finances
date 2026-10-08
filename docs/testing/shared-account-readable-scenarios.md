# Escenarios legibles de cuentas compartidas

Este documento reemplaza como criterio esperado a la matriz CSV previa mientras se corrigen las reglas de cuentas compartidas. El objetivo es usar fichas cortas, sin tablas, para validar la logica de negocio antes de tocar codigo.

## Criterio principal

Cuando una cuenta compartida tiene balance positivo y custodia positiva, un egreso pagado de bolsillo debe generar deuda desde la custodia de la cuenta hacia los usuarios que participan en el egreso.

La deuda no va del usuario hacia la cuenta.

Cuando el egreso se paga con fondos de la cuenta, el comportamiento depende de si los fondos alcanzan:

- Si el balance y la custodia alcanzan, el egreso solo baja balance y custodia.
- Si el egreso supera el balance/custodia disponible, el balance puede quedar negativo y la diferencia genera deuda desde los usuarios participantes hacia la cuenta segun su porcentaje.
- Para descontar custodia, se usa primero la custodia del mismo usuario cuando aplique, y despues la custodia de otros usuarios.
- Una cuenta con balance negativo se comporta como cuenta de gastos. Si despues entra dinero y el balance vuelve a positivo, vuelve a comportarse como cuenta de ahorro.
- Un ingreso posterior no cubre automaticamente deudas de usuarios. Las deudas siguen activas hasta que se registren sus pagos.
- Si la custodia del usuario responsable no alcanza en un egreso con fondos, el faltante se interpreta como dinero puesto de bolsillo por el pagador operativo, salvo que el movimiento indique explicitamente que se registra en nombre de otro custodio.

Ejemplo conceptual:

- La cuenta tiene balance `1000`.
- A custodia `1000`.
- Se registra un egreso de bolsillo por `500`.
- El balance economico baja a `500`.
- La custodia sigue mostrando `1000` hasta que se pague la deuda.
- Se genera una deuda desde la custodia hacia los usuarios por `500`.
- Cuando se paga la deuda, baja la custodia correspondiente.
- El balance no vuelve a bajar al pagar la deuda, porque ya bajo al crear el egreso.

## Lenguaje comun

Usar estas frases en pruebas y UI cuando sea posible:

- `A custodia N de la cuenta`
- `B custodia N de la cuenta`
- `La cuenta debe pagar N a A`
- `La cuenta debe pagar N a B`
- `La custodia de A debe pagar N a A`
- `La custodia de A debe pagar N a B`
- `La custodia de B debe pagar N a B`
- `A debe aportar N a la cuenta`
- `B debe aportar N a la cuenta`

Evitar `settlement` en escenarios de negocio. Si aparece en codigo o datos internos, debe mapearse a una frase de deuda legible.

## Reglas esperadas

### Resumen ejecutivo

La cuenta compartida debe mantener cuatro conceptos separados:

- Balance economico: ingresos menos egresos; puede ser positivo o negativo.
- Custodia: quien tiene el dinero cuando el balance es positivo.
- Deudas por bolsillo: la cuenta/custodios deben pagar a usuarios que participaron en el egreso.
- Deudas por fondos insuficientes: usuarios deben aportar a la cuenta cuando el egreso supera los fondos.

Edge cases criticos:

- Egreso con fondos que supera el balance y deja la cuenta negativa.
- Ingreso posterior que vuelve positivo el balance sin liquidar deudas anteriores.
- Egreso con bolsillo y balance positivo, donde la cuenta debe pagar a usuarios.
- Custodia propia insuficiente en pago con fondos, donde el faltante se interpreta como bolsillo del pagador operativo.
- Edicion de movimientos con deudas pendientes no completadas.

### Regla 1: egreso con fondos de la cuenta

Si el egreso se paga con fondos de la cuenta:

- Si el balance alcanza para cubrir el total, el balance baja por el monto total del egreso.
- Si la custodia alcanza para cubrir el total, la custodia baja por el monto total del egreso.
- La custodia se descuenta priorizando primero la custodia del mismo usuario y despues la custodia de otros usuarios.
- Si el total se cubre con balance/custodia disponible, no se genera deuda.
- Si el total supera el balance/custodia disponible, la diferencia genera deuda desde los usuarios participantes hacia la cuenta segun el porcentaje del egreso.
- Si la custodia del usuario responsable no alcanza, el faltante se interpreta como bolsillo del pagador operativo y genera deuda desde el custodio correspondiente hacia ese pagador.
- El usuario que aparece como pagador es operativo; no significa que puso dinero personal.

Ejemplo con fondos suficientes:

- Balance inicial: `1000`.
- Egreso: `300`.
- Division: 50/50.
- Balance final: `700`.
- Custodia total baja `300`.
- Deudas pendientes: ninguna.

Ejemplo con fondos insuficientes:

- Balance inicial: `1000`.
- Egreso: `1200`.
- Division: 50/50.
- Balance final: `-200`.
- Diferencia no cubierta: `200`.
- A debe aportar `100` a la cuenta.
- B debe aportar `100` a la cuenta.

### Regla 2: egreso pagado de bolsillo

Si el egreso se paga de bolsillo:

- El balance baja por el monto total del egreso.
- La custodia no baja inmediatamente.
- Se genera deuda desde los custodios hacia los usuarios participantes del egreso.
- Al pagar la deuda, baja la custodia correspondiente.
- El pago de la deuda no cambia el balance economico.

Nota:

Esta regla de deuda es distinta a fondos insuficientes. En bolsillo, la cuenta/custodia debe pagar a usuarios. En fondos insuficientes, los usuarios deben aportar a la cuenta.

### Regla 3: prioridad de custodio

Cuando haya que descontar custodia o generar deuda desde custodios hacia usuarios:

1. Primero se usa la custodia del mismo usuario beneficiario.
2. Si esa custodia no alcanza en un pago con fondos, el faltante se interpreta como bolsillo del pagador operativo.
3. Si el pagador operativo registro el gasto en nombre de otro custodio, debe existir una forma explicita de indicarlo; no debe inferirse automaticamente.
4. Si hay varios custodios posibles, la regla de reparto debe ser explicita antes de implementarse.

La deuda por faltante cubierto de bolsillo:

- No cambia el balance.
- No aumenta la custodia del pagador.
- Reduce la custodia del usuario que debe cubrir el faltante cuando se paga.
- Representa que el pagador operativo completo de bolsillo una compra marcada como fondos.
- Debe ser visible y accionable en la app.

Consecuencia:

- Despues de pagar esta deuda, la custodia total puede quedar por debajo del balance positivo.
- Esa diferencia representa dinero de la cuenta que ya fue reconocido economicamente en el balance, pero fue reembolsado al bolsillo del pagador.
- No debe corregirse automaticamente aumentando la custodia de otro usuario.

### Regla 4: edicion de egresos con deuda

Al editar un egreso que genero deudas:

- Las deudas no completadas del egreso original deben recalcularse.
- No deben quedar deudas pendientes obsoletas.
- Las deudas ya completadas no deben borrarse silenciosamente.
- Si una deuda completada queda inconsistente por la edicion, debe existir una correccion explicita o una regla de compensacion visible.

Esta regla es critica porque ya hubo un incidente donde editar un movimiento dejo registros de libro y balance inconsistentes.

## Base comun A

Estado inicial:

- Cuenta compartida entre A y B.
- Balance: `1000`.
- Custodia de A: `1000`.
- Custodia de B: `0`.

## Escenario A01

Nombre: A registra egreso, A paga con fondos, sin dividir.

Movimiento:

- Registra: A.
- Pagador operativo: A.
- Fuente: fondos de la cuenta.
- Division: no.
- Monto: `500`.

Esperado:

- Balance final: `500`.
- Custodia de A: `500`.
- Custodia de B: `0`.
- Deudas pendientes: ninguna.

## Escenario A02

Nombre: A registra egreso, B aparece como pagador operativo, con fondos, sin dividir.

Movimiento:

- Registra: A.
- Pagador operativo: B.
- Fuente: fondos de la cuenta.
- Division: no.
- Monto: `500`.

Esperado:

- Balance final: `500`.
- Custodia de A: `500`.
- Custodia de B: `0`.
- Deudas pendientes: ninguna.

Nota:

Como se usan fondos de la cuenta, B no puso dinero de bolsillo aunque aparezca como pagador operativo.

## Base comun Fondos B

Estado inicial:

- Cuenta compartida entre A y B.
- Balance: `1000`.
- Custodia de A: `500`.
- Custodia de B: `500`.

## Escenario F01

Nombre: egreso con fondos, sin dividir, custodio propio suficiente.

Movimiento:

- Registra: A.
- Pagador operativo: A.
- Fuente: fondos de la cuenta.
- Division: no.
- Monto: `300`.

Esperado:

- Balance final: `700`.
- Custodia de A: `200`.
- Custodia de B: `500`.
- Deudas pendientes: ninguna.

Nota:

Como el egreso corresponde a A y A tiene custodia suficiente, se descuenta primero de la custodia de A.

## Escenario F02

Nombre: egreso con fondos, sin dividir, custodio propio insuficiente.

Movimiento:

- Registra: A.
- Pagador operativo: A.
- Fuente: fondos de la cuenta.
- Division: no.
- Monto: `700`.

Esperado:

- Balance final: `300`.
- Custodia de A: `0`.
- Custodia de B: `300`.
- Deudas pendientes: ninguna.

Nota:

El egreso corresponde a A. Primero se consumen los `500` custodiados por A y despues `200` de la custodia de B.

## Escenario F03

Nombre: egreso con fondos, dividido 50/50, fondos suficientes.

Movimiento:

- Fuente: fondos de la cuenta.
- Division: 50/50.
- Monto: `300`.

Esperado:

- Balance final: `700`.
- Custodia de A: `350`.
- Custodia de B: `350`.
- Deudas pendientes: ninguna.

Nota:

El total se cubre con balance y custodias disponibles. Se descuenta `150` de la custodia de A y `150` de la custodia de B por prioridad de custodio propio.

## Escenario F04

Nombre: egreso con fondos, dividido 50/50, fondos insuficientes.

Movimiento:

- Fuente: fondos de la cuenta.
- Division: 50/50.
- Monto: `1200`.

Esperado al crear:

- Balance final: `-200`.
- Custodia de A: `0`.
- Custodia de B: `0`.
- Diferencia no cubierta por fondos: `200`.
- Deuda pendiente 1: A debe aportar `100` a la cuenta.
- Deuda pendiente 2: B debe aportar `100` a la cuenta.

Estado de las deudas:

- Las deudas quedan pendientes aunque la cuenta quede negativa.
- Cuando entren nuevos fondos custodiados, esas deudas no se pagan automaticamente.
- Las deudas deben poder pagarse manualmente despues, aumentando balance y custodia sin alterar el egreso original.

Nota:

El balance y las custodias cubren `1000`. El excedente es `200`, dividido 50/50 entre A y B. La cuenta queda negativa porque el egreso economico completo fue `1200`.

## Escenario F05

Nombre: egreso con fondos deja balance negativo y luego entra ingreso custodiado.

Estado inicial:

- Cuenta compartida entre A y B.
- Balance: `1000`.
- Custodia de A: `1000`.
- Custodia de B: `0`.

Movimiento 1:

- Fuente: fondos de la cuenta.
- Division: 50/50.
- Monto del egreso: `1500`.

Esperado despues del egreso:

- Balance final: `-500`.
- Custodia de A: `0`.
- Custodia de B: `0`.
- Deuda pendiente 1: A debe aportar `250` a la cuenta.
- Deuda pendiente 2: B debe aportar `250` a la cuenta.

Movimiento 2:

- Tipo: ingreso.
- Monto: `1000`.
- Custodio: A.

Esperado despues del ingreso:

- Balance final: `500`.
- Custodia de A: `500`.
- Custodia de B: `0`.
- Deuda pendiente 1: A debe aportar `250` a la cuenta.
- Deuda pendiente 2: B debe aportar `250` a la cuenta.

Nota:

El ingreso vuelve positiva la cuenta, pero no liquida automaticamente las deudas del egreso anterior.

Esperado despues de pagar la deuda de A:

- Balance final: `750`.
- Custodia de A: `750`.
- Custodia de B: `0`.
- Deuda pendiente: B debe aportar `250` a la cuenta.

Esperado despues de pagar la deuda de B:

- Balance final: `1000`.
- Custodia de A: `1000`.
- Custodia de B: `0`.
- Deudas pendientes: ninguna.

## Escenario F06

Nombre: egreso con fondos, custodio propio insuficiente y faltante cubierto por pagador.

Estado inicial:

- Cuenta compartida entre A y B.
- Balance: `1500`.
- Custodia de A: `1000`.
- Custodia de B: `500`.

Movimiento:

- Registra: B.
- Pagador operativo: B.
- Fuente: fondos de la cuenta.
- Division: no.
- Usuario participante del egreso: B.
- Monto del egreso: `700`.

Esperado al crear:

- Balance final: `800`.
- Custodia de A: `800`.
- Custodia de B: `0`.
- Deudas por fondos insuficientes: ninguna, porque el balance total si alcanzo.
- Deuda pendiente: la custodia de A debe pagar `200` a B.

Interpretacion:

- B tenia `500` custodiados y el egreso fue de `700`.
- Los primeros `500` salen de la custodia de B.
- Los `200` restantes se entienden como dinero que B puso de bolsillo para completar el pago.
- Como A todavia custodia fondos positivos de la cuenta, A debe reembolsar esos `200` a B desde su custodia.

Esperado al pagar la deuda:

- Balance final: `800`.
- Custodia de A: `600`.
- Custodia de B: `0`.
- La accion no aumenta la custodia de B porque B recibe el dinero como reembolso de bolsillo, no como custodia nueva.
- La accion no cambia el balance porque el egreso ya afecto el balance al crearse.
- La deuda queda cerrada.

Nota de uso cotidiano:

- Si B registra el gasto porque A, que custodia el dinero, fue quien realmente pago o debia pagarlo, la app necesita una forma explicita de marcar que el gasto se registra en nombre de A.
- Sin esa marca explicita, el pagador operativo B se interpreta como quien completo el faltante de bolsillo.

## Escenario A03

Nombre: A registra egreso, A paga de bolsillo, sin dividir.

Movimiento:

- Registra: A.
- Pagador: A.
- Fuente: bolsillo.
- Division: no.
- Monto: `500`.

Esperado al crear:

- Balance final: `500`.
- Custodia de A: `1000`.
- Custodia de B: `0`.
- Deuda pendiente: la custodia de A debe pagar `500` a A.

Esperado al pagar la deuda:

- Balance final: `500`.
- Custodia de A: `500`.
- Custodia de B: `0`.
- Deudas pendientes: ninguna.

## Escenario A04

Nombre: A registra egreso, B paga de bolsillo, sin dividir.

Movimiento:

- Registra: A.
- Pagador: B.
- Fuente: bolsillo.
- Division: no.
- Monto: `500`.

Esperado al crear:

- Balance final: `500`.
- Custodia de A: `1000`.
- Custodia de B: `0`.
- Deuda pendiente: la custodia de A debe pagar `500` a A.

Esperado al pagar la deuda:

- Balance final: `500`.
- Custodia de A: `500`.
- Custodia de B: `0`.
- Deudas pendientes: ninguna.

Nota:

Sin division, el usuario participante del egreso es A. El pagador de bolsillo no cambia el beneficiario de la deuda si la responsabilidad del gasto sigue siendo de A.

## Escenario A05

Nombre: A registra egreso, A paga de bolsillo, dividido 50/50.

Movimiento:

- Registra: A.
- Pagador: A.
- Fuente: bolsillo.
- Division: 50/50.
- Monto: `500`.

Esperado al crear:

- Balance final: `500`.
- Custodia de A: `1000`.
- Custodia de B: `0`.
- Deuda pendiente 1: la custodia de A debe pagar `250` a A.
- Deuda pendiente 2: la custodia de A debe pagar `250` a B.

Esperado al pagar ambas deudas:

- Balance final: `500`.
- Custodia de A: `500`.
- Custodia de B: `0`.
- Deudas pendientes: ninguna.

Nota:

La deuda se genera hacia los usuarios participantes del egreso segun su asignacion. El pagador de bolsillo no recibe automaticamente el total si el egreso esta dividido.

## Escenario A06

Nombre: B registra egreso, B paga de bolsillo, dividido 50/50.

Movimiento:

- Registra: B.
- Pagador: B.
- Fuente: bolsillo.
- Division: 50/50.
- Monto: `500`.

Esperado al crear:

- Balance final: `500`.
- Custodia de A: `1000`.
- Custodia de B: `0`.
- Deuda pendiente 1: la custodia de A debe pagar `250` a A.
- Deuda pendiente 2: la custodia de A debe pagar `250` a B.

Esperado al pagar ambas deudas:

- Balance final: `500`.
- Custodia de A: `500`.
- Custodia de B: `0`.
- Deudas pendientes: ninguna.

## Base comun B

Estado inicial:

- Cuenta compartida entre A y B.
- Balance: `1000`.
- Custodia de A: `500`.
- Custodia de B: `500`.

## Escenario B01

Nombre: egreso de bolsillo dividido con custodia balanceada.

Movimiento:

- Fuente: bolsillo.
- Division: 50/50.
- Monto: `200`.

Esperado al crear:

- Balance final: `800`.
- Custodia de A: `500`.
- Custodia de B: `500`.
- Deuda pendiente 1: la custodia de A debe pagar `100` a A.
- Deuda pendiente 2: la custodia de B debe pagar `100` a B.

Esperado al pagar ambas deudas:

- Balance final: `800`.
- Custodia de A: `400`.
- Custodia de B: `400`.
- Deudas pendientes: ninguna.

Nota:

Se prioriza la custodia del mismo usuario antes de usar la custodia del otro.

## Base comun C

Estado inicial:

- Cuenta compartida entre A y B.
- Balance: `1500`.
- Custodia de A: `1000`.
- Custodia de B: `500`.

## Escenario C01

Nombre: egreso grande de bolsillo dividido con custodia insuficiente de B.

Movimiento:

- Fuente: bolsillo.
- Division: 50/50.
- Monto: `1200`.

Esperado al crear:

- Balance final: `300`.
- Custodia de A: `1000`.
- Custodia de B: `500`.
- Deuda pendiente 1: la custodia de A debe pagar `600` a A.
- Deuda pendiente 2: la custodia de B debe pagar `500` a B.
- Deuda pendiente 3: la custodia de A debe pagar `100` a B.

Esperado al pagar todas las deudas:

- Balance final: `300`.
- Custodia de A: `300`.
- Custodia de B: `0`.
- Deudas pendientes: ninguna.

Nota:

La parte de B es `600`. Primero se usa su propia custodia disponible, `500`. El faltante, `100`, sale de la custodia de A.

## Escenarios de edicion

### Edicion E01

Nombre: egreso con deudas pendientes cambia de monto.

Estado inicial:

- Existe un egreso por `500`.
- Tiene deudas pendientes no pagadas.

Edicion:

- El monto cambia a `300`.

Esperado:

- El balance se recalcula con el nuevo egreso.
- Las deudas pendientes del egreso original se reemplazan por deudas calculadas sobre `300`.
- No queda deuda pendiente por el monto anterior.

### Edicion E02

Nombre: egreso de bolsillo cambia a fondos de cuenta.

Estado inicial:

- Existe un egreso de bolsillo.
- Tiene deudas pendientes no pagadas.

Edicion:

- La fuente cambia de bolsillo a fondos de la cuenta.

Esperado:

- Se eliminan o cierran las deudas pendientes no pagadas generadas por bolsillo.
- El balance queda afectado por el egreso.
- La custodia baja como egreso pagado desde fondos.
- No queda deuda de bolsillo pendiente.

### Edicion E03

Nombre: egreso con fondos cambia a bolsillo.

Estado inicial:

- Existe un egreso pagado con fondos.
- No tiene deudas de bolsillo.

Edicion:

- La fuente cambia de fondos a bolsillo.

Esperado:

- El balance queda afectado por el egreso.
- La custodia deja de tener el descuento inmediato de fondos.
- Se generan deudas desde custodios hacia usuarios participantes segun las reglas de prioridad.

### Edicion E04

Nombre: egreso cambia de division.

Estado inicial:

- Existe un egreso de bolsillo dividido 50/50.
- Tiene deudas pendientes no pagadas.

Edicion:

- La division cambia a sin dividir o cambia porcentajes.

Esperado:

- Las deudas pendientes no pagadas se recalculan con las nuevas asignaciones.
- Las deudas viejas no deben seguir apareciendo.
- El balance se mantiene segun el monto vigente del egreso.

## Preguntas abiertas

- Si la custodia total no alcanza para cubrir la deuda generada por un egreso de bolsillo, falta definir si se crea deuda parcial hasta la custodia disponible o si se permite custodia negativa.
- Si una deuda ya fue pagada y luego se edita el egreso original, falta definir si se crea un ajuste compensatorio automatico o si se bloquea la edicion.
- Si hay mas de dos custodios y el custodio propio no alcanza, falta definir el orden exacto para consumir custodias ajenas.
