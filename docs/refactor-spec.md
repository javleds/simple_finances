# Refactor Spec

## Diagnostico Inicial

La aplicacion tiene varias paginas actuando como "todo en uno": orquestan API, estado local, permisos, filtros, modales, formularios y presentacion.

Tamano actual mas relevante:

- `AccountTransactionsPage.vue`: 1025 lineas
- `AccountsPage.vue`: 657 lineas
- `SubscriptionsPage.vue`: 649 lineas
- `AccountUsersPage.vue`: 567 lineas
- `AccountGoalsPage.vue`: 555 lineas
- `AccountInvitationsPage.vue`: 526 lineas
- `DistributionPage.vue`: 490 lineas
- `DashboardPage.vue`: 426 lineas
- Shared complejos: `AppPercentageSplitEditor`, `AppDatePicker`, `AppInput`, `AppModal`, `AppSearchSelect`, `AppActionMenu`

## Objetivo

Dejar las paginas como capas de composicion delgadas. La logica debe vivir en composables, la UI repetible en componentes especializados, y los contratos API en schemas/repositorios. La funcionalidad actual se preserva con refactors pequenos, verificables y por modulo.

## Plan Paso A Paso

### 1. Crear reglas base de arquitectura

Definir una convencion clara por modulo:

- `pages`: composicion de la vista, sin logica compleja.
- `components`: presentacion y eventos.
- `composables`: estado, filtros, modales, acciones y orquestacion.
- `repositories`: API.
- `schemas`: parseo/mapeo.
- `types`: contratos de dominio/frontend.

Criterio: una pagina idealmente debe quedar entre 120 y 220 lineas. Si supera eso, debe tener una justificacion clara.

### 2. Auditar patrones duplicados antes de tocar codigo

Buscar duplicados en:

- filtros por `search/status/type`
- modales CRUD
- listas con loading/error/empty/infinite scroll
- confirmaciones de delete/complete
- form state `{ canSubmit, isSubmitting }`
- formato de moneda/fecha
- paginacion y reload
- acciones por permisos del usuario activo

Output esperado: una lista corta de candidatos a shared/composables.

### 3. Refactor piloto: `AccountGoalsPage.vue`

Es buen primer objetivo porque tiene tamano alto, pero menor riesgo que `AccountTransactionsPage`.

Extraer:

- `useAccountGoalsPageState`
- `useAccountGoalFilters`
- `useAccountGoalModals`
- `AccountGoalsToolbar.vue`
- `AccountGoalsList.vue`
- `AccountGoalDeleteModal.vue`

Meta: bajar `AccountGoalsPage.vue` de ~555 lineas a ~180-220.

### 4. Crear componentes shared para estados de lista

Repetido en muchas paginas:

- loading inicial
- error inicial con retry
- error parcial
- empty state
- infinite scroll footer

Propuesta:

- `AppListState.vue`
- `AppEmptyState.vue`
- `AppLoadMoreFooter.vue`

Despues actualizar `AGENTS.md` con estos shared components, como pide la regla del repo.

### 5. Crear composables shared de filtros

Paginas como transactions, goals, invitations, accounts repiten:

- sincronizar query params
- parsear arrays desde query
- limpiar filtros
- toggles de chips

Propuesta:

- `useQuerySyncedFilters`
- `useToggleFilterGroup`

Criterio: no meter reglas de negocio aqui. Solo mecanica reusable.

### 6. Crear composables shared de modales CRUD

Muchas paginas tienen:

- `selectedId`
- `isCreateOpen`
- `isEditOpen`
- `isDeleteOpen`
- open/close/clear errors

Propuesta:

- `useSelectionModalState`
- `useCrudModalState`

Mantener nombres explicitos en cada pagina para no perder legibilidad.

### 7. Refactor `AccountInvitationsPage` y `AccountUsersPage`

Despues del piloto, aplicar el mismo patron a paginas vecinas del mismo modulo.

Extraer componentes:

- toolbar/filtros
- list section
- item modals
- page-specific action composables

Meta: paginas bajo 220 lineas cada una.

### 8. Refactor mayor: `AccountTransactionsPage.vue`

Esta debe ser fase separada por riesgo. Tiene balance, pending por usuario, infinite scroll, CRUD, complete individual, complete batch, filtros, formularios y side effects.

Extraer:

- `useAccountTransactionsHeader`
- `useAccountTransactionFilters`
- `useAccountTransactionActions`
- `useAccountTransactionModals`
- `AccountTransactionsHeader.vue`
- `AccountTransactionsToolbar.vue`
- `AccountTransactionsList.vue`
- `CompletePendingByUserModal.vue`
- `CompleteTransactionModal.vue`

Regla importante: conservar la logica reciente de balance/pending visible con tests antes de moverla.

### 9. Refactor formularios grandes

Candidatos:

- `TransactionsForm.vue`: 296 lineas
- `AccountsForm.vue`: 266 lineas
- `SubscriptionsForm.vue`: 219 lineas

Extraer secciones internas cuando tengan responsabilidad clara:

- account selector
- status/type toggles
- payment split section
- recurrence section
- date/cancellation section

No crear microcomponentes para 10 lineas. Solo secciones con estado o reglas propias.

### 10. Revisar shared components complejos

`AppPercentageSplitEditor`, `AppDatePicker`, `AppInput`, `AppModal`, `AppSearchSelect`, `AppActionMenu` ya son shared, pero algunos son grandes.

No partirlos primero. Antes conviene:

- mejorar nombres internos
- extraer helpers puros a `.ts`
- agregar tests a reglas complejas
- mantener API publica estable

### 11. Layouts

`AdminLayout.vue` tiene ~286 lineas. Conviene separar:

- `AdminSidebar.vue`
- `AdminTopBar.vue`
- `AdminMobileNav.vue`
- `AdminUserMenu.vue`
- composable `useAdminNavigation`

Meta: layout solo decide estructura y slots/router-view.

### 12. Dashboard

Ya empezo a mejorar con `DashboardBalanceChart.vue`.

Siguiente:

- `DashboardSummaryCards.vue`
- `DashboardPendingActions.vue`
- `DashboardSubscriptionsPlanning.vue`
- `useDashboardPendingActions`
- `useDashboardChartFilters`

### 13. Repositorios y schemas

No mover por mover. Pero si separar archivos muy grandes cuando mezclen dominios:

- `accountSchemas.ts` puede dividirse en account, members, pending, write payloads.
- `transactionsRepository.ts` puede separar schemas internos si crece mas.

Criterio: el repo debe leer como transporte; los schemas como contrato; la pagina nunca debe conocer payload API crudo.

### 14. Testing por fases

Prioridad de tests:

- reglas de pending/balance en transactions
- filtros query params
- permisos de acciones
- batch complete parcial/fallido
- mappers de schemas criticos

Evitar tests fragiles de markup salvo componentes shared criticos.

### 15. Orden recomendado de ejecucion

1. Crear shared list states.
2. Refactor `AccountGoalsPage`.
3. Refactor `AccountInvitationsPage`.
4. Refactor `AccountUsersPage`.
5. Refactor `DashboardPage`.
6. Refactor `AccountTransactionsPage`.
7. Refactor `AccountsPage`.
8. Refactor `SubscriptionsPage`.
9. Refactor `DistributionPage`.
10. Revisar shared complejos y schemas grandes.

## Criterios De Aceptacion

- Ninguna pagina principal sobre 250 lineas salvo excepcion justificada.
- Componentes de presentacion sin llamadas API directas.
- Composables con nombres de caso de uso, no genericos artificiales.
- Repositorios sin logica visual.
- Shared components registrados en `AGENTS.md`.
- `npm run build` pasando en cada fase.
- Tests agregados donde haya reglas o side effects, no solo snapshots.

## Progreso

### 2026-06-11

Commits aplicados:

- `2b4326b refactor(shared): add reusable list state components`
- `fc2b46e refactor(goals): use shared list state components`
- `961df21 refactor(goals): extract goals toolbar`
- `c79444f refactor(goals): extract filters modal`
- `53f4364 refactor(goals): extract goals list`
- `d3ad98f refactor(goals): extract goal modals`
- `de9e7f7 refactor(goals): extract query synced filters`
- `a781fdf refactor(goals): extract modal state`
- `3bc3b41 refactor(goals): extract modal actions`
- `0aaae37 refactor(invitations): use shared list state components`
- `cb7b2d4 refactor(invitations): extract invitations toolbar`
- `7f6b913 refactor(invitations): extract filters modal`
- `2654516 refactor(invitations): extract invitations list`
- `c22402a refactor(invitations): extract invitation modals`
- `e196ac7 refactor(invitations): extract query synced filters`
- `1adecc4 refactor(invitations): extract modal state`
- `fb9ea82 refactor(invitations): extract modal actions`
- `a5b861a refactor(invitations): extract page actions`
- `dddcb2c refactor(users): use shared list state components`
- `2cab660 refactor(users): extract users toolbar`
- `aef3222 refactor(users): extract split editor`
- `2de3c2f refactor(users): extract user modals`
- `ae0b47f refactor(users): extract users crud`
- `11e5ad7 refactor(users): extract query synced filters`
- `49d6931 refactor(users): extract modal state`
- `0253755 refactor(users): extract modal actions`
- `a79408a refactor(users): extract split draft state`
- `1b1b79c refactor(users): extract page actions`
- `b1c4f4b refactor(dashboard): extract summary cards`
- `8c2ed48 refactor(dashboard): extract balance section`
- `b30698c refactor(dashboard): extract pending actions`
- `586f3f5 refactor(dashboard): extract subscriptions planning`
- `d7ba1ed refactor(dashboard): extract complete pending modal`
- `848d846 refactor(dashboard): extract pending action state`
- `fb9c870 refactor(transactions): use shared list state components`
- `e9a9d48 refactor(transactions): extract transactions toolbar`
- `37ae352 refactor(transactions): extract filters modal`
- `53b7ea7 refactor(transactions): extract transactions header`
- `d3db068 refactor(transactions): extract transaction form modal`
- `9787dd5 refactor(transactions): extract confirmation modals`
- `3731c70 refactor(transactions): extract query synced filters`
- `7934be6 refactor(transactions): extract modal actions`
- `899f23b refactor(transactions): extract modal state`
- `0be6cc7 refactor(transactions): extract page actions`
- `aab786d refactor(transactions): extract pending state`
- `9622770 refactor(transactions): extract list loader`
- `4a0f55a refactor(transactions): share permission rules`

Estado actual:

- `AccountGoalsPage.vue` bajo de ~555 lineas a 292 lineas.
- Se agregaron shared components:
  - `AppListState.vue`
  - `AppEmptyState.vue`
  - `AppLoadMoreFooter.vue`
- `AGENTS.md` y el barrel `src/modules/shared/components/index.ts` ya incluyen los shared components nuevos.
- La UI de metas quedo separada en:
  - `AccountGoalsToolbar.vue`
  - `AccountGoalFiltersModal.vue`
  - `AccountGoalsList.vue`
  - `AccountGoalFormModal.vue`
  - `AccountGoalDeleteModal.vue`
- La logica de metas quedo parcialmente separada en:
  - `useAccountGoalFilters.ts`
  - `useAccountGoalModals.ts`
  - `useAccountGoalModalActions.ts`
- `AccountInvitationsPage.vue` bajo de ~526 lineas a 237 lineas.
- La UI de invitaciones quedo separada en:
  - `AccountInvitationsToolbar.vue`
  - `AccountInvitationFiltersModal.vue`
  - `AccountInvitationsList.vue`
  - `AccountInvitationFormModal.vue`
  - `AccountInvitationDeleteModal.vue`
- La logica de invitaciones quedo separada en:
  - `useAccountInvitationFilters.ts`
  - `useAccountInvitationModals.ts`
  - `useAccountInvitationModalActions.ts`
  - `useAccountInvitationActions.ts`
- `AccountUsersPage.vue` bajo de ~567 lineas a 243 lineas.
- La UI de usuarios quedo separada en:
  - `AccountUsersToolbar.vue`
  - `AccountUsersSplitEditor.vue`
  - `AccountUsersList.vue`
  - `AccountUserCreateModal.vue`
  - `AccountUserEditModal.vue`
  - `AccountUserDeleteModal.vue`
- La logica de usuarios quedo separada en:
  - `useAccountUserFilters.ts`
  - `useAccountUsersCrud.ts`
  - `useAccountUserModals.ts`
  - `useAccountUserModalActions.ts`
  - `useAccountUsersSplitDraft.ts`
  - `useAccountUserActions.ts`
- `DashboardPage.vue` bajo de ~426 lineas a 126 lineas.
- La UI del dashboard quedo separada en:
  - `DashboardBalanceSection.vue`
  - `DashboardSummaryCards.vue`
  - `DashboardPendingActions.vue`
  - `DashboardSubscriptionsPlanning.vue`
  - `DashboardCompletePendingModal.vue`
- La logica de pendientes del dashboard quedo separada en:
  - `useDashboardPendingActions.ts`
- `AccountTransactionsPage.vue` bajo de ~1025 lineas a 325 lineas en la fase actual.
- La UI de transacciones quedo parcialmente separada en:
  - `AccountTransactionsHeader.vue`
  - `AccountTransactionsToolbar.vue`
  - `AccountTransactionsList.vue`
  - `AccountTransactionFiltersModal.vue`
  - `AccountTransactionFormModal.vue`
  - `AccountTransactionDeleteModal.vue`
  - `AccountTransactionCompleteModal.vue`
  - `AccountCompletePendingByUserModal.vue`
- La logica de filtros y acciones de modales de transacciones quedo separada en:
  - `useAccountTransactionFilters.ts`
  - `useAccountTransactionModals.ts`
  - `useAccountTransactionModalActions.ts`
  - `useAccountTransactionActions.ts`
  - `useAccountTransactionsPendingState.ts`
  - `useAccountTransactionListLoader.ts`
- Las reglas puras de permisos de transacciones quedaron centralizadas en:
  - `src/modules/transactions/lib/transactionPermissions.ts`

Verificacion:

- `npm run build` paso despues de cada commit funcional.

Siguiente paso recomendado:

1. Agregar pruebas enfocadas para `useAccountTransactionsPendingState.ts`, especialmente balance, ocultamiento del usuario completado y completado parcial/fallido.
2. Evaluar si `AccountTransactionsPage.vue` requiere una ultima extraccion pequena o si ya puede considerarse aceptable temporalmente.
3. Continuar con `AccountsPage.vue`, `SubscriptionsPage.vue` y `DistributionPage.vue`.
