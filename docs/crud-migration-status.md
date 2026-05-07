# CRUD Migration Status

## Completed

These modules already follow the new CRUD pattern with:

- typed domain models,
- zod schemas,
- repository layer,
- CRUD composable,
- form composable,
- modal-connected validation,
- disabled submit while invalid,
- page-level orchestration.

### Accounts

- page:
  [AccountsPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/pages/AccountsPage.vue)
- form:
  [AccountsForm.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/components/AccountsForm.vue)
- composables:
  [useAccountsCrud.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/composables/useAccountsCrud.ts)
  [useAccountForm.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/composables/useAccountForm.ts)
- repository:
  [accountsRepository.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/repositories/accountsRepository.ts)
- schemas:
  [accountSchemas.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/schemas/accountSchemas.ts)

### Transactions

- page:
  [AccountTransactionsPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/pages/AccountTransactionsPage.vue)
- form:
  [TransactionsForm.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/transactions/components/TransactionsForm.vue)
- composables:
  [useTransactionsCrud.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/transactions/composables/useTransactionsCrud.ts)
  [useTransactionForm.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/transactions/composables/useTransactionForm.ts)
- repository:
  [transactionsRepository.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/transactions/repositories/transactionsRepository.ts)
- schemas:
  [transactionSchemas.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/transactions/schemas/transactionSchemas.ts)

### Subscriptions

- page:
  [SubscriptionsPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/subscriptions/pages/SubscriptionsPage.vue)
- form:
  [SubscriptionsForm.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/subscriptions/components/SubscriptionsForm.vue)
- composables:
  [useSubscriptionsCrud.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/subscriptions/composables/useSubscriptionsCrud.ts)
  [useSubscriptionForm.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/subscriptions/composables/useSubscriptionForm.ts)
- repository:
  [subscriptionsRepository.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/subscriptions/repositories/subscriptionsRepository.ts)
- schemas:
  [subscriptionSchemas.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/subscriptions/schemas/subscriptionSchemas.ts)

### Financial Goals

- page:
  [AccountGoalsPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/pages/AccountGoalsPage.vue)
- form:
  [AccountGoalForm.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/components/AccountGoalForm.vue)
- composables:
  [useAccountGoalsCrud.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/composables/useAccountGoalsCrud.ts)
  [useAccountGoalForm.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/composables/useAccountGoalForm.ts)
- repository:
  [accountGoalsRepository.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/repositories/accountGoalsRepository.ts)
- schemas:
  [accountGoalSchemas.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/schemas/accountGoalSchemas.ts)

### Account Invites

- page:
  [AccountInvitationsPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/pages/AccountInvitationsPage.vue)
- form:
  [AccountInvitationForm.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/components/AccountInvitationForm.vue)
- composables:
  [useAccountInvitesCrud.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/composables/useAccountInvitesCrud.ts)
  [useAccountInviteForm.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/composables/useAccountInviteForm.ts)
- repository:
  [accountInvitesRepository.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/repositories/accountInvitesRepository.ts)
- schemas:
  [accountInviteSchemas.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/schemas/accountInviteSchemas.ts)

## Shared Foundation Completed

- API client:
  [apiClient.ts](/Users/javleds/dev/javleds/finsi_20/src/lib/api/apiClient.ts)
- env typing:
  [env.d.ts](/Users/javleds/dev/javleds/finsi_20/env.d.ts)
- env example:
  [.env.example](/Users/javleds/dev/javleds/finsi_20/.env.example)
- reusable input error state:
  [AppInput.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/shared/components/AppInput.vue)
- reusable datepicker error state:
  [AppDatePicker.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/shared/components/AppDatePicker.vue)

## Not Completed

These areas were not migrated to the CRUD pattern in this pass.

### Account Users

Reason:

- `ABOUT.md` describes inline editing of percentages inside the relation manager, not a standalone create/edit form.
- this needs a dedicated inline-edit interaction and a scoped update flow rather than the same modal form pattern.

Current file:

- [AccountUsersPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/pages/AccountUsersPage.vue)

### Global Invitations Page

Reason:

- `InvitationsPage.vue` is not a standard CRUD. It is a response flow for invitations received by the authenticated user.
- actions are `accept` and `reject`, not create/edit/delete.

Current file:

- [InvitationsPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/admin/pages/InvitationsPage.vue)

### Distribution

Reason:

- this facility is a frontend concept in the current repo, but it is not described in `ABOUT.md` as a backend resource with a confirmed form contract.
- it needs explicit backend contract confirmation before applying the same CRUD structure.

Current files:

- [DistributionPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/distribution/pages/DistributionPage.vue)
- [DistributionDetailPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/distribution/pages/DistributionDetailPage.vue)

### Settings

Reason:

- this is a configuration screen, not a CRUD with modal forms and list items.

Current file:

- [ConfigPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/settings/pages/ConfigPage.vue)

### Dashboard

Reason:

- this is a read-heavy orchestration screen with lightweight task actions, not a module CRUD.

Current file:

- [DashboardPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/admin/pages/DashboardPage.vue)

## Next Recommended Pass

If migration continues, recommended order is:

1. `AccountUsersPage.vue`
2. `InvitationsPage.vue`
3. `DistributionPage.vue` and `DistributionDetailPage.vue`

That order preserves the highest business value with the least ambiguity.
