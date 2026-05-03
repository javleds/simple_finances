# Building CRUDs

This document explains how to replicate the `accounts` CRUD architecture in other modules.

Reference implementation:

- [src/modules/accounts/pages/AccountsPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/pages/AccountsPage.vue)
- [src/modules/accounts/components/AccountsForm.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/components/AccountsForm.vue)
- [src/modules/accounts/composables/useAccountsCrud.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/composables/useAccountsCrud.ts)
- [src/modules/accounts/composables/useAccountForm.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/composables/useAccountForm.ts)
- [src/modules/accounts/repositories/accountsRepository.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/repositories/accountsRepository.ts)
- [src/modules/accounts/schemas/accountSchemas.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/schemas/accountSchemas.ts)
- [src/modules/accounts/types.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/types.ts)
- [src/lib/api/apiClient.ts](/Users/javleds/dev/javleds/finsi_20/src/lib/api/apiClient.ts)

Related diagram:

- [docs/building-cruds-flow.puml](/Users/javleds/dev/javleds/finsi_20/docs/building-cruds-flow.puml)

## Goal

Each feature CRUD should have:

1. domain types,
2. zod schemas for API parsing and form validation,
3. a repository dedicated to API communication,
4. one composable for CRUD state,
5. one composable for form state,
6. a page that orchestrates modals and list state,
7. a form component that only renders fields and emits valid payloads.

This keeps responsibilities explicit and reusable.

## Directory Structure

For a module named `subscriptions`, use this shape:

```text
src/modules/subscriptions/
  components/
    SubscriptionsForm.vue
    SubscriptionListItem.vue
  composables/
    useSubscriptionForm.ts
    useSubscriptionsCrud.ts
  pages/
    SubscriptionsPage.vue
  repositories/
    subscriptionsRepository.ts
  schemas/
    subscriptionSchemas.ts
  types.ts
```

The API client stays shared in:

```text
src/lib/api/apiClient.ts
```

## Step 1: Define the Domain Types

Create `src/modules/<module>/types.ts`.

Separate at least these concepts:

- domain entity used by the UI,
- form values used by the form,
- write payload sent to the backend.

Example pattern:

```ts
export type Subscription = {
  id: string;
  name: string;
  amount: number;
  isActive: boolean;
};

export type SubscriptionFormValues = {
  name: string;
  amount: string;
  isActive: 'yes' | 'no';
};

export type SubscriptionWritePayload = {
  name: string;
  amount: number;
  isActive: boolean;
};
```

Why split them:

- the backend contract is rarely the same as the form contract,
- the form often stores numbers as strings,
- the UI entity should already be normalized.

## Step 2: Create Zod Schemas

Create `src/modules/<module>/schemas/<module>Schemas.ts`.

This file should contain three things:

1. form schema,
2. API response schema,
3. mapping helpers.

For the `accounts` module this lives in [accountSchemas.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/schemas/accountSchemas.ts).

### 2.1 Form Schema

Use zod for field-level and cross-field validation.

Typical responsibilities:

- required fields,
- string trimming,
- conditional requirements,
- numeric ranges,
- business rules that belong to the form.

Pattern:

```ts
export const subscriptionFormSchema = z.object({
  name: z.string().trim().min(1, 'El nombre es obligatorio.'),
  amount: z.string().trim().min(1, 'El monto es obligatorio.'),
  isActive: z.enum(['yes', 'no']),
});
```

If there are dependencies between fields, use `superRefine`.

### 2.2 API Schema

Parse the raw backend response into a predictable structure before it reaches the UI.

Typical responsibilities:

- convert `id` to `string`,
- convert booleans like `1`, `0`, `true`, `false`,
- convert nullable numbers,
- rename backend fields only through mapping helpers.

Pattern:

```ts
export const subscriptionApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform(String),
  name: z.string(),
  amount: z.unknown().transform(parseNullableNumber),
  active: z.unknown().transform(parseBooleanLike),
});
```

### 2.3 Mapping Helpers

At minimum create:

- `createDefault<Form>Values`
- `map<Api>ToDomain`
- `map<Form>ToWritePayload`

These helpers are critical because they isolate transformations.

Do not scatter these conversions across page, form, and repository files.

## Step 3: Create the Repository

Create `src/modules/<module>/repositories/<module>Repository.ts`.

The repository is the only place that knows:

- endpoint paths,
- collection vs single-resource response shapes,
- how to transform the write payload into backend field names.

For `accounts`, this is [accountsRepository.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/repositories/accountsRepository.ts).

Pattern:

```ts
export function createSubscriptionsRepository() {
  return {
    async list(): Promise<Subscription[]> {},
    async create(payload: SubscriptionWritePayload): Promise<Subscription> {},
    async update(id: string, payload: SubscriptionWritePayload): Promise<Subscription> {},
    async remove(id: string): Promise<void> {},
  };
}
```

Repository rules:

- never return raw API payloads,
- always parse API responses with zod,
- always return domain entities,
- keep endpoint strings here,
- keep backend naming differences here.

## Step 4: Reuse the Shared API Client

Use [apiClient.ts](/Users/javleds/dev/javleds/finsi_20/src/lib/api/apiClient.ts).

Environment variable:

```env
VITE_API_BASE_URL=http://localhost:8000/api
```

This is documented in [.env.example](/Users/javleds/dev/javleds/finsi_20/.env.example) and typed in [env.d.ts](/Users/javleds/dev/javleds/finsi_20/env.d.ts).

Rules:

- do not call `fetch` directly from pages,
- do not call `fetch` directly from form components,
- keep transport concerns centralized.

## Step 5: Create the CRUD Composable

Create `src/modules/<module>/composables/use<ModulePlural>Crud.ts`.

This composable owns list and mutation state.

For `accounts`, see [useAccountsCrud.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/composables/useAccountsCrud.ts).

It should expose:

- list state,
- loading state,
- saving state,
- deleting state,
- load error,
- save error,
- delete error,
- CRUD methods,
- error reset helpers if needed by modals.

Pattern:

```ts
export function useSubscriptionsCrud() {
  const items = ref<Subscription[]>([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  async function loadSubscriptions() {}
  async function createSubscription(payload: SubscriptionWritePayload) {}
  async function updateSubscription(id: string, payload: SubscriptionWritePayload) {}
  async function deleteSubscription(id: string) {}

  return {};
}
```

Rules:

- this composable talks to the repository,
- it should not know UI layout,
- it should return simple booleans for success when that helps page orchestration,
- it should translate thrown errors into user-facing strings.

## Step 6: Create the Form Composable

Create `src/modules/<module>/composables/use<Module>Form.ts`.

For `accounts`, see [useAccountForm.ts](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/composables/useAccountForm.ts).

This composable owns:

- `vee-validate` setup,
- zod schema connection with `toTypedSchema`,
- initial values,
- field adapters,
- derived UI flags like `showCreditFields`,
- submit normalization,
- `isSubmitDisabled`.

Pattern:

```ts
const {
  errors,
  handleSubmit,
  meta,
  resetForm,
  setFieldValue,
  values,
} = useForm<FormValues>({
  validationSchema: toTypedSchema(formSchema),
  initialValues,
  validateOnMount: true,
});
```

Recommended output contract:

- one computed per field,
- `errors`,
- `meta`,
- `isSubmitting`,
- `isSubmitDisabled`,
- `submitForm`,
- any derived booleans for conditional sections.

Rules:

- the form composable should emit normalized write payloads,
- pages should not manually rebuild payloads from raw strings,
- conditional clearing logic belongs here, not in the page.

## Step 7: Build the Form Component

Create `src/modules/<module>/components/<Module>Form.vue`.

For `accounts`, see [AccountsForm.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/components/AccountsForm.vue).

This component should:

- render the fields,
- consume the form composable,
- display validation errors next to the corresponding control,
- emit valid payloads only,
- emit form state so the modal footer can enable or disable submit.

Expected props:

- `formId`
- `initialValues`
- `serverError`

Expected emits:

- `submit`
- `stateChange`

The form component should not:

- call the repository directly,
- open or close modals,
- decide whether this is create or edit,
- own list reload logic.

## Step 8: Connect Validation to Shared Inputs

Shared primitives must accept error state.

For example, [AppInput.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/shared/components/AppInput.vue) now supports:

- `error?: string`
- invalid border color,
- invalid label color,
- inline error message,
- `aria-invalid`

When creating a CRUD for another module:

- prefer extending a shared input primitive once,
- do not re-implement field error UI in every form if the component can absorb it cleanly.

## Step 9: Build the Page Orchestrator

Create or adapt `src/modules/<module>/pages/<ModulePlural>Page.vue`.

For `accounts`, see [AccountsPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/pages/AccountsPage.vue).

The page should own:

- search term,
- filters,
- modal open/close state,
- selected item id,
- form footer state,
- calls into the CRUD composable,
- modal action definitions.

The page should not:

- parse backend payloads,
- build fetch requests,
- revalidate field rules manually,
- normalize forms by hand.

### Recommended Page Responsibilities

For create modal:

1. clear stale save errors,
2. reset footer state,
3. open modal,
4. receive `stateChange` from form,
5. pass `disabled` into `AppModal` footer action,
6. call `create<Module>()` on submit,
7. close modal only on success.

For edit modal:

1. store selected item id,
2. pass selected item as `initialValues`,
3. use same form component,
4. call `update<Module>()`,
5. close modal only on success.

For delete modal:

1. store selected item id,
2. show summary text,
3. call `delete<Module>()`,
4. close modal only on success.

## Step 10: Disable Submit When the Form Is Invalid

This is already implemented in `accounts`.

Flow:

1. `useAccountForm` computes `isSubmitDisabled`.
2. `AccountsForm` emits `stateChange`.
3. `AccountsPage` stores that state.
4. `AppModal` action receives `disabled: true` when needed.

This is the recommended pattern for all future CRUDs because:

- footer actions live outside the form,
- the modal remains generic,
- the form keeps validation ownership.

## Step 11: Reuse the Same Form for Create and Edit

Do not create separate `CreateXForm` and `EditXForm` unless they truly diverge.

Preferred pattern:

- one form component,
- one form composable,
- `initialValues` prop,
- page decides whether it is create or edit by choosing which mutation to call.

## Step 12: Keep Mock Data Out of the CRUD Path

Once a module gets real CRUD integration:

- the list should come from the CRUD composable,
- page filters should use the loaded entities,
- list items should consume the domain type from `types.ts`,
- old `data/*.ts` mocks should remain only for screens not yet migrated.

`accounts` already follows this in [AccountsPage.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/pages/AccountsPage.vue) and [AccountListItem.vue](/Users/javleds/dev/javleds/finsi_20/src/modules/accounts/components/AccountListItem.vue).

## Step 13: Error Handling Rules

Keep error handling split by responsibility:

- API client:
  raises `ApiError`
- repository:
  parses response shape
- CRUD composable:
  converts thrown errors into user-facing strings
- page:
  decides where to render them
- form:
  only renders `serverError` and field errors

This keeps backend failures and form validation failures separate.

## Step 14: Naming Conventions

Recommended names:

- `types.ts`
- `schemas/<module>Schemas.ts`
- `repositories/<module>Repository.ts`
- `composables/use<ModulePlural>Crud.ts`
- `composables/use<Module>Form.ts`
- `components/<ModulePlural>Form.vue`

Examples:

- `transactionsRepository.ts`
- `useTransactionsCrud.ts`
- `useTransactionForm.ts`
- `TransactionsForm.vue`

## Step 15: Checklist for a New CRUD

When building a new module CRUD, follow this order:

1. Create `types.ts`.
2. Create `schemas/<module>Schemas.ts`.
3. Define:
   - API schema
   - form schema
   - default values mapper
   - API-to-domain mapper
   - form-to-write mapper
4. Create `repositories/<module>Repository.ts`.
5. Create `composables/use<ModulePlural>Crud.ts`.
6. Create `composables/use<Module>Form.ts`.
7. Build `<Module>Form.vue`.
8. Extend shared inputs only if the field primitive truly needs it.
9. Connect the page modals to:
   - `serverError`
   - `stateChange`
   - disabled modal actions
10. Load list data from the CRUD composable.
11. Validate with:
   - `npm run type-check`
   - `npx eslint <files>`

## Step 16: Anti-Patterns to Avoid

Avoid these:

- `fetch` directly inside a page
- form validation rules declared only in template attributes
- converting API payloads inside the template
- one-off inline form state in each page
- separate create/edit forms with duplicated markup
- list item components typed against mock data once the module has a real repository
- modal submit buttons that are always enabled

## Minimal Template

Use this as a starting skeleton for another module:

```ts
// types.ts
export type ModuleEntity = {};
export type ModuleFormValues = {};
export type ModuleWritePayload = {};
```

```ts
// schemas/moduleSchemas.ts
export const moduleFormSchema = z.object({});
export const moduleApiSchema = z.object({});
export function createDefaultModuleFormValues() {}
export function mapModuleApiToDomain() {}
export function mapModuleFormToWritePayload() {}
```

```ts
// repositories/modulesRepository.ts
export function createModulesRepository() {
  return {
    list() {},
    create() {},
    update() {},
    remove() {},
  };
}
```

```ts
// composables/useModulesCrud.ts
export function useModulesCrud() {}
```

```ts
// composables/useModuleForm.ts
export function useModuleForm() {}
```

```vue
<!-- components/ModulesForm.vue -->
<script setup lang="ts">
// render only
</script>
```

```vue
<!-- pages/ModulesPage.vue -->
<script setup lang="ts">
// orchestrate only
</script>
```

## Final Rule

If a file starts doing too many of these at once:

- rendering,
- validation,
- fetching,
- mapping,
- modal orchestration,
- error translation,

split it.

That is the main rule behind the `accounts` CRUD implementation.
