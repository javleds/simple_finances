# Categorías de transacciones

Las categorías clasifican ingresos y egresos de forma opcional. No modifican importes, saldos, asignaciones, custodia ni reembolsos.

## Catálogos y permisos

| Catálogo | Alcance | Crear y seleccionar | Renombrar y eliminar |
| --- | --- | --- | --- |
| Personal | Usuario; reutilizable en sus cuentas individuales | El usuario titular | El usuario titular |
| Compartido | Una cuenta compartida | Todos los miembros de la cuenta | Solo el propietario de la cuenta |

Cada categoría pertenece a un usuario o a una cuenta, nunca a ambos. Una transacción solo puede usar una categoría del catálogo efectivo de su cuenta. Las categorías compartidas no se incorporan al catálogo personal de los miembros ni están disponibles en otras cuentas compartidas.

Los nombres son obligatorios y admiten hasta 100 caracteres. Se recortan los extremos, se agrupan espacios repetidos y se ignoran mayúsculas para detectar duplicados dentro del mismo catálogo. Dos catálogos diferentes pueden contener «Alimentación» sin conflicto: son categorías independientes.

## Al compartir una cuenta

Al aceptar una invitación o incorporar miembros, la primera conversión crea el catálogo compartido copiando únicamente las categorías usadas por los movimientos de esa cuenta. Esos movimientos pasan a referenciar las copias; el catálogo personal y los movimientos de otras cuentas se conservan. Las conversiones posteriores no duplican categorías.

El miembro que entra puede consultar, crear y seleccionar categorías del catálogo compartido. Sus categorías personales permanecen separadas. `accounts.uses_shared_categories` conserva el catálogo compartido aunque la cuenta vuelva a tener un único miembro.

## Captura y consulta

El formulario prioriza Concepto → Cantidad → Fecha → Categoría. Cantidad y Fecha comparten fila en escritorio y se apilan en móvil; los datos de pago y reparto aparecen después de Categoría.

El selector permite buscar, crear una categoría y seleccionarla sin abandonar el formulario. Crear una categoría la guarda inmediatamente: cancelar después la transacción no elimina esa categoría. La interfaz identifica el catálogo compartido y la cuenta a la que pertenece. En los listados, la categoría aparece debajo del concepto.

«Sin categoría» representa `category_id = null`; no es una categoría llamada «Default» ni un registro especial. Los movimientos anteriores a la funcionalidad permanecen sin categoría. Al editar, omitir `category_id` conserva el valor existente; enviar `null` quita la clasificación.

## Administración y eliminación

Más → Categorías permite consultar los catálogos disponibles y su uso. El propietario puede renombrar categorías compartidas; el cambio de nombre se refleja en todos los movimientos que las usan.

Al eliminar una categoría con movimientos, el usuario debe elegir:

- **Dejar los movimientos sin categoría**: conservarlos y quitar su clasificación.
- **Mover los movimientos a otra categoría**: seleccionar una categoría distinta del mismo catálogo.

Una categoría sin uso puede eliminarse sin elegir destino. El backend comprueba el uso durante la eliminación, incluso si cambió desde que se abrió la confirmación. La reasignación y la eliminación se ejecutan en una transacción de base de datos.

En un catálogo compartido, la operación puede reclasificar movimientos registrados por otros miembros. La interfaz advierte sobre ese alcance y muestra el uso de la categoría. Este permiso de clasificación del propietario no concede permisos adicionales para editar importes o eliminar transacciones ajenas.

## Contrato API

Todas las rutas requieren autenticación JWT. Las rutas de cuenta verifican membresía y resuelven su catálogo efectivo; para una cuenta individual corresponden al catálogo personal.

| Operación | Personal | Por cuenta |
| --- | --- | --- |
| Listar | `GET /api/categories` | `GET /api/accounts/{account}/categories` |
| Crear | `POST /api/categories` | `POST /api/accounts/{account}/categories` |
| Renombrar | `PUT /api/categories/{category}` | `PUT /api/accounts/{account}/categories/{category}` |
| Eliminar | `DELETE /api/categories/{category}` | `DELETE /api/accounts/{account}/categories/{category}` |

Listar admite `search` y devuelve `data` con categorías, `transactions_count` y `can_manage`; `meta` incluye `scope`, `account_id`, `account_name` y `can_manage`. Crear y renombrar reciben `name`. Eliminar recibe `action: "uncategorize"` o `action: "reassign"`; la segunda opción requiere `target_category_id`. Una categoría usada exige una acción explícita.

Los contratos de transacciones incluyen `category_id` y la categoría relacionada. Laravel valida el catálogo y los permisos; las capacidades mostradas por Vue no sustituyen esa autorización.

## Referencias

- [Especificación de implementación](../specs/004-categorias-personales-y-compartidas.md).
- [Cuentas compartidas](shared-accounts.md).
- [Estrategia de pruebas](../testing/strategy.md#categorías-personales-y-compartidas).
