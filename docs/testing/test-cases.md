# Matriz de pruebas para cuentas compartidas

> Propuesta original de expectativas de negocio, conservada como referencia. No sustituye las reglas vigentes de [cuentas compartidas](../shared-account-model.md) ni las pruebas automatizadas.

## Objetivo

Definir expectativas de negocio para cuentas compartidas antes de revisar o modificar implementación.

La matriz debe permitir validar:

- balance económico de la cuenta;
- custodia por usuario;
- deuda/reembolso por usuario;
- efectos de registrar reembolsos;
- efectos de editar movimientos;
- efectos de registrar ingresos posteriores.

## Estado base común

Todos los escenarios parten de esta cuenta:

| Campo | Valor |
| --- | ---: |
| Cuenta | Compartida entre Usuario A y Usuario B |
| Balance inicial | 1000.00 |
| Custodia inicial A | 1000.00 |
| Custodia inicial B | 0.00 |
| Deuda inicial A | 0.00 |
| Deuda inicial B | 0.00 |
| Monto estándar de egreso | 500.00 |
| Split estándar | A 50% / B 50% |
| Monto estándar de ingreso posterior | 500.00 |

## Convenciones

| Término | Significado esperado |
| --- | --- |
| Actor | Usuario autenticado que registra o edita el movimiento. |
| Pagador | Usuario indicado como quien pagó el egreso. |
| Custodio | Usuario indicado como quien custodia un ingreso. |
| Fondos de cuenta | El egreso se paga con dinero existente de la cuenta. Debe reducir balance y custodia. |
| Bolsillo | El egreso fue pagado fuera de la cuenta por un miembro. Debe reducir balance económico y generar deuda/reembolso. |
| Sin dividir | El responsable económico del egreso es el actor que registra el movimiento. |
| Dividir 50/50 | La responsabilidad económica del egreso es 250.00 para A y 250.00 para B. |
| Deuda A | Monto neto que A debe pagar. Negativo en settlement de A. |
| Deuda B | Monto neto que B debe pagar. Negativo en settlement de B. |
| Por recibir A | Monto neto que A debe recibir. Positivo en settlement de A. |
| Por recibir B | Monto neto que B debe recibir. Positivo en settlement de B. |

## Decisiones esperadas de producto

Estas reglas son la base de la matriz. Si alguna no representa el comportamiento deseado, se debe ajustar la matriz antes de escribir o cambiar tests.

1. El balance económico siempre cambia por transacciones económicas:
   - ingreso completado suma al balance;
   - egreso completado resta al balance;
   - reembolsos entre miembros no cambian el balance.

2. La custodia representa quién tiene físicamente o bancariamente dinero de la cuenta:
   - ingresos aumentan custodia del custodio elegido;
   - egresos con fondos de cuenta reducen custodia del custodio que tenía fondos disponibles;
   - egresos de bolsillo no cambian custodia;
   - reembolsos no cambian custodia.

3. La deuda/reembolso representa responsabilidad entre miembros:
   - egresos de bolsillo generan deuda hacia el pagador según la responsabilidad;
   - egresos con fondos de cuenta no generan deuda si se interpreta que la cuenta cubrió el gasto;
   - si se desea que "dividir usando fondos" genere deuda entre miembros, esa regla debe definirse explícitamente porque cambia el significado de fondos de cuenta.

4. Para esta propuesta, "sin dividir" significa que el responsable del egreso es el actor, no necesariamente el pagador.

5. Para esta propuesta, "pagador" en fondos de cuenta es informativo/operativo. Como el dinero salió de la cuenta, no debe crear deuda hacia el pagador.

## Resultado base esperado por tipo de movimiento

### Egreso de 500 con fondos de cuenta

| Resultado | Valor |
| --- | ---: |
| Balance final | 500.00 |
| Custodia A | 500.00 |
| Custodia B | 0.00 |
| Settlement A | 0.00 |
| Settlement B | 0.00 |
| Reembolso pendiente | Ninguno |

### Egreso de 500 de bolsillo, sin dividir

Si el actor y el pagador son el mismo usuario, no hay reembolso pendiente porque pagó quien era responsable.

Si el actor y el pagador son distintos, el actor debe al pagador 500.00.

### Egreso de 500 de bolsillo, dividido 50/50

El pagador cubre 500.00. Su propia parte de 250.00 queda cubierta. El otro usuario debe 250.00 al pagador.

## Matriz A: crear egresos sin dividir

| ID | Nombre descriptivo | Actor | Pagador | Fuente | Dividir | Balance final | Custodia A | Custodia B | Settlement A | Settlement B | Reembolso pendiente esperado |
| --- | --- | --- | --- | --- | --- | ---: | ---: | ---: | ---: | ---: | --- |
| A01 | A registra, A paga, fondos, sin dividir | A | A | Fondos | No | 500.00 | 500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| A02 | A registra, B opera pago, fondos, sin dividir | A | B | Fondos | No | 500.00 | 500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| A03 | B registra, A opera pago, fondos, sin dividir | B | A | Fondos | No | 500.00 | 500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| A04 | B registra, B paga, fondos, sin dividir | B | B | Fondos | No | 500.00 | 500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| A05 | A registra, A paga, bolsillo, sin dividir | A | A | Bolsillo | No | 500.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| A06 | A registra, B paga, bolsillo, sin dividir | A | B | Bolsillo | No | 500.00 | 1000.00 | 0.00 | -500.00 | 500.00 | A paga 500.00 a B |
| A07 | B registra, A paga, bolsillo, sin dividir | B | A | Bolsillo | No | 500.00 | 1000.00 | 0.00 | 500.00 | -500.00 | B paga 500.00 a A |
| A08 | B registra, B paga, bolsillo, sin dividir | B | B | Bolsillo | No | 500.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |

## Matriz B: crear egresos divididos 50/50

| ID | Nombre descriptivo | Actor | Pagador | Fuente | Dividir | Balance final | Custodia A | Custodia B | Settlement A | Settlement B | Reembolso pendiente esperado |
| --- | --- | --- | --- | --- | --- | ---: | ---: | ---: | ---: | ---: | --- |
| B01 | A registra, A paga, fondos, dividido 50/50 | A | A | Fondos | 50/50 | 500.00 | 500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| B02 | A registra, B opera pago, fondos, dividido 50/50 | A | B | Fondos | 50/50 | 500.00 | 500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| B03 | B registra, A opera pago, fondos, dividido 50/50 | B | A | Fondos | 50/50 | 500.00 | 500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| B04 | B registra, B paga, fondos, dividido 50/50 | B | B | Fondos | 50/50 | 500.00 | 500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| B05 | A registra, A paga, bolsillo, dividido 50/50 | A | A | Bolsillo | 50/50 | 500.00 | 1000.00 | 0.00 | 250.00 | -250.00 | B paga 250.00 a A |
| B06 | A registra, B paga, bolsillo, dividido 50/50 | A | B | Bolsillo | 50/50 | 500.00 | 1000.00 | 0.00 | -250.00 | 250.00 | A paga 250.00 a B |
| B07 | B registra, A paga, bolsillo, dividido 50/50 | B | A | Bolsillo | 50/50 | 500.00 | 1000.00 | 0.00 | 250.00 | -250.00 | B paga 250.00 a A |
| B08 | B registra, B paga, bolsillo, dividido 50/50 | B | B | Bolsillo | 50/50 | 500.00 | 1000.00 | 0.00 | -250.00 | 250.00 | A paga 250.00 a B |

## Matriz C: registrar reembolsos después de crear deuda

Estos escenarios parten del caso indicado, no del estado base puro.

| ID | Caso origen | Acción | Balance final | Custodia A | Custodia B | Settlement A | Settlement B | Reembolso pendiente esperado |
| --- | --- | --- | ---: | ---: | ---: | ---: | ---: | --- |
| C01 | A06 | A registra pago de 500.00 a B | 500.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| C02 | A07 | B registra pago de 500.00 a A | 500.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| C03 | B05 | B registra pago de 250.00 a A | 500.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| C04 | B06 | A registra pago de 250.00 a B | 500.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| C05 | B07 | B registra pago de 250.00 a A | 500.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| C06 | B08 | A registra pago de 250.00 a B | 500.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| C07 | B06 | A registra pago parcial de 100.00 a B | 500.00 | 1000.00 | 0.00 | -150.00 | 150.00 | A paga 150.00 a B |
| C08 | A06 | A intenta registrar pago mayor de 600.00 a B | 500.00 | 1000.00 | 0.00 | 100.00 | -100.00 | B paga 100.00 a A, o rechazar sobrepago |

## Matriz D: crear y editar fuente, pagador y división

Todos estos escenarios empiezan desde el estado base, crean un egreso de 500 y luego editan el mismo movimiento.

| ID | Nombre descriptivo | Crear | Editar a | Balance final | Custodia A | Custodia B | Settlement A | Settlement B | Reembolso pendiente esperado |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: | ---: | --- |
| D01 | Fondos sin dividir pasa a bolsillo sin dividir, mismo actor/pagador | A01 | A05 | 500.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| D02 | Fondos sin dividir pasa a bolsillo sin dividir, pagador B | A01 | A06 | 500.00 | 1000.00 | 0.00 | -500.00 | 500.00 | A paga 500.00 a B |
| D03 | Bolsillo sin dividir con deuda pasa a fondos sin dividir | A06 | A02 | 500.00 | 500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| D04 | Bolsillo sin dividir con deuda pasa a bolsillo dividido | A06 | B06 | 500.00 | 1000.00 | 0.00 | -250.00 | 250.00 | A paga 250.00 a B |
| D05 | Bolsillo dividido pasa a bolsillo sin dividir | B06 | A06 | 500.00 | 1000.00 | 0.00 | -500.00 | 500.00 | A paga 500.00 a B |
| D06 | Fondos dividido pasa a bolsillo dividido, A paga | B01 | B05 | 500.00 | 1000.00 | 0.00 | 250.00 | -250.00 | B paga 250.00 a A |
| D07 | Bolsillo dividido, pagador B, cambia monto de 500 a 800 | B06 | Monto 800, bolsillo, dividido 50/50 | 200.00 | 1000.00 | 0.00 | -400.00 | 400.00 | A paga 400.00 a B |
| D08 | Bolsillo dividido, pagador B, cambia monto de 500 a 300 | B06 | Monto 300, bolsillo, dividido 50/50 | 700.00 | 1000.00 | 0.00 | -150.00 | 150.00 | A paga 150.00 a B |
| D09 | Fondos sin dividir cambia monto de 500 a 800 | A01 | Monto 800, fondos, sin dividir | 200.00 | 200.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| D10 | Fondos sin dividir cambia monto de 500 a 1200 | A01 | Monto 1200, fondos, sin dividir | Rechazado | Sin cambio | Sin cambio | Sin cambio | Sin cambio | Debe rechazarse por fondos insuficientes |

## Matriz E: crear egreso y después registrar ingresos

Estos escenarios validan que los ingresos posteriores solo aumentan balance y custodia del custodio, sin borrar deudas existentes.

| ID | Caso origen | Ingreso posterior | Custodio del ingreso | Balance final | Custodia A | Custodia B | Settlement A | Settlement B | Reembolso pendiente esperado |
| --- | --- | --- | --- | ---: | ---: | ---: | ---: | ---: | --- |
| E01 | A01 | 500.00 registrado por A | A | 1000.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| E02 | A01 | 500.00 registrado por A | B | 1000.00 | 500.00 | 500.00 | 0.00 | 0.00 | Ninguno |
| E03 | A06 | 500.00 registrado por A | A | 1000.00 | 1500.00 | 0.00 | -500.00 | 500.00 | A paga 500.00 a B |
| E04 | A06 | 500.00 registrado por A | B | 1000.00 | 1000.00 | 500.00 | -500.00 | 500.00 | A paga 500.00 a B |
| E05 | B06 | 500.00 registrado por B | A | 1000.00 | 1500.00 | 0.00 | -250.00 | 250.00 | A paga 250.00 a B |
| E06 | B06 | 500.00 registrado por B | B | 1000.00 | 1000.00 | 500.00 | -250.00 | 250.00 | A paga 250.00 a B |

## Matriz F: crear egreso, registrar reembolso y después registrar ingresos

| ID | Flujo | Balance final | Custodia A | Custodia B | Settlement A | Settlement B | Reembolso pendiente esperado |
| --- | --- | ---: | ---: | ---: | ---: | ---: | --- |
| F01 | A06, luego A paga 500 a B, luego ingreso 500 custodiado por A | 1000.00 | 1500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| F02 | A06, luego A paga 500 a B, luego ingreso 500 custodiado por B | 1000.00 | 1000.00 | 500.00 | 0.00 | 0.00 | Ninguno |
| F03 | B06, luego A paga 250 a B, luego ingreso 500 custodiado por A | 1000.00 | 1500.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| F04 | B06, luego A paga 250 a B, luego ingreso 500 custodiado por B | 1000.00 | 1000.00 | 500.00 | 0.00 | 0.00 | Ninguno |

## Matriz G: eliminar movimientos

| ID | Caso origen | Acción | Balance final | Custodia A | Custodia B | Settlement A | Settlement B | Reembolso pendiente esperado |
| --- | --- | --- | ---: | ---: | ---: | ---: | ---: | --- |
| G01 | A01 | Eliminar egreso | 1000.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| G02 | A06 | Eliminar egreso | 1000.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| G03 | B06 | Eliminar egreso | 1000.00 | 1000.00 | 0.00 | 0.00 | 0.00 | Ninguno |
| G04 | C04 | Eliminar egreso después de reembolso | Definir | Definir | Definir | Definir | Definir | Decidir si se rechaza o se revierte también el reembolso |

## Matriz H: casos borde e invariantes

| ID | Nombre descriptivo | Configuración | Expectativa |
| --- | --- | --- | --- |
| H01 | Fondos insuficientes | Balance 0.00, egreso 500 con fondos | Rechazar operación |
| H02 | Custodia positiva menor que egreso con fondos | Balance 1000, custodia A 300, custodia B 700, egreso 500 con fondos | Consumir 300 de A y 200 de B, balance 500 |
| H03 | Egreso de bolsillo mayor que balance | Balance 1000, egreso 1500 de bolsillo pagado por B y dividido | Balance -500, custodia sin cambio, A debe 750 a B |
| H04 | Reembolso parcial | A debe 500 a B, A paga 200 | A sigue debiendo 300 a B |
| H05 | Reembolso exacto | A debe 500 a B, A paga 500 | Deuda cerrada, custodia sin cambio |
| H06 | Reembolso mayor a deuda | A debe 500 a B, A paga 600 | Decidir: rechazar sobrepago o crear deuda inversa de 100 |
| H07 | Editar pagador de bolsillo | Caso B06 cambia pagador de B a A | Deuda cambia de "A paga 250 a B" a "B paga 250 a A" |
| H08 | Editar actor no permitido | B intenta editar movimiento de A sin autorización | Rechazar por autorización si aplica |
| H09 | Ingreso sin custodio explícito | A registra ingreso 500 sin custodio | Custodio por defecto debe ser A |
| H10 | Egreso dividido con porcentajes inválidos | Split 60/30 | Rechazar validación porque no suma 100 |

## Siguiente paso sugerido

1. Confirmar o ajustar las decisiones esperadas de producto.
2. Convertir cada fila aceptada en tests backend con nombres equivalentes al ID.
3. Crear un helper de assertions para balance, custodia, settlement y reembolsos pendientes.
4. Ejecutar los tests contra el código actual para identificar divergencias reales.
5. Corregir implementación solo después de que la matriz esté aprobada.
