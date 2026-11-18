# Custodios virtuales por cuenta

## Contexto
La custodia y los reembolsos referencian usuarios. Se necesita representar depósitos externos sin otorgar acceso, notificaciones ni participación en gastos.

## Objetivo
Gestionar custodios virtuales desde Usuarios, conservar el dinero de la cuenta al retirarlos y permitir seleccionarlos como pagadores.

## Alcance
Alta con saldo adicional opcional y transferencias de custodia; listado integrado; eliminación con distribución íntegra entre miembros; pagos, reembolsos, resúmenes e historial coherentes.

## Fuera de alcance
Rendimientos, integración bancaria, custodios entre cuentas y cambios de estilos.

## Comportamiento esperado
Crear suma únicamente el saldo adicional. Transferir custodia conserva el balance. Eliminar requiere distribuir exactamente la custodia positiva y resolver obligaciones pendientes; conserva la identidad histórica. El pagador virtual puede pagar de bolsillo más que su custodia. No participa automáticamente en repartos.

## Decisiones técnicas
Conservar las referencias financieras existentes mediante usuarios técnicos con `virtual_account_id` y `virtual_retired_at`. No insertar estos usuarios en `account_user`. Separar participantes financieros de miembros autorizados. Bloquear autenticación, recuperación, invitaciones y notificaciones de identidades virtuales. No modificar identidades históricas.

## Componentes afectados
Modelos compartidos Account/User, resúmenes del dominio, autorizaciones de participantes, servicios de transacciones y transferencias, API y módulos Vue de cuentas/transacciones.

## Backend
Acciones explícitas para crear y retirar custodios con transacción y bloqueo de cuenta. Montos en centavos para verificar conservación y límites. Mantener nombres e IDs en el libro tras retirar.

## Frontend
Reutilizar AccountUsersToolbar, AccountUsersList/AccountUserListItem, AppActionMenu, AppModal, AppInput y AppPercentageSplitEditor. Añadir modalidad monetaria al editor sin alterar su modalidad porcentual. Slider e importe en una fila; saldos resultantes debajo. Custodios en la misma lista, con eliminar como única acción. Invalidar cuentas, dashboard, transacciones y dinero personal.

## Persistencia
Migración aditiva generada con Artisan; marcador nullable de cuenta virtual y fecha de retirada en users. En transacciones, `virtual_custodian_opening` distingue el saldo inicial y `uses_virtual_custodian_rules` preserva el modo financiero histórico. Mantener claves financieras y datos actuales. El rollback requiere no haber generado identidades virtuales.

## API
GET/POST `/api/accounts/{account}/custodians`; DELETE `/api/accounts/{account}/custodians/{user}`. Crear: name, opening_balance y transfers [{user_id, amount}]. Retirar: distributions [{user_id, amount}]. Respuestas de cuenta incluyen virtual_custodians; los IDs siguen siendo IDs financieros existentes.

## Validaciones
Importes finitos, no negativos y con dos decimales; origen con custodia suficiente; destino miembro vigente; ámbito de cuenta obligatorio; suma exacta al retirar; bloqueo de retiradas con obligaciones pendientes. Los custodios retirados no son seleccionables en nuevas operaciones.

## Permisos y seguridad
Todos los miembros vigentes pueden leer, crear y retirar custodios virtuales. La identidad virtual no autentica ni concede acceso a una cuenta y no es invitada ni notificada.

## Casos límite
Custodia cero, cantidades de un centavo, saldo adicional y transferencia combinados, deuda pendiente, pagador distinto al autor, operaciones concurrentes, retiro e historial, ediciones y borrados posteriores.

## Estrategia de pruebas
API de creación y retiro, conservación de balance/custodia, aislamiento entre cuentas, permisos, autenticación denegada, reembolsos a pagador real/virtual, exclusión de repartos y notificaciones. Pruebas de formularios y cálculos monetarios.

## Estrategia de validación
Sincronizar paquete en Finance y MCP. Ejecutar PHPUnit del paquete/MCP, pruebas Finance, type-check, Vitest y build. Verificar formatos y diff.

## Estrategia de commits
Commits convencionales en inglés para dominio/API e interfaz, manteniendo estados coherentes y verificados.

## Archivos estimados a modificar
`packages/finance-domain/src/{Models,ReadModels,Authorization}`, `apps/finance/app/{Services,Http}`, migraciones y pruebas de Finance, módulos Vue accounts/transactions y editor compartido.

## Riesgos
La identidad financiera no debe filtrarse a autenticación o membresías. Las ediciones históricas no deben dejar custodia huérfana. La modalidad monetaria necesita precisión de centavos independiente del redondeo porcentual.

## Preguntas abiertas
Ninguna que impida implementar el flujo acordado. Se preservan las restricciones existentes de cuentas compartidas.

## Plan de implementación
1. Incorporar marcador y relaciones financieras sin cambiar membresías; verificar aislamiento.
2. Crear acciones/endpoints transaccionales y pruebas de conservación y permisos.
3. Integrar pagadores, reembolsos y resúmenes preservando reglas reales; probar ciclo de transacciones.
4. Incorporar modales/listado y modo monetario reutilizando componentes; ejecutar pruebas UI.
5. Sincronizar consumidores, ejecutar validaciones y revisar el diff antes de commits locales.
