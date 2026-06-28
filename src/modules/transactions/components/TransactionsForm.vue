<script setup lang="ts">
import { computed, watch } from 'vue';

import type { AccountMember } from '@/modules/accounts/types';
import { useTransactionForm } from '@/modules/transactions/composables/useTransactionForm';
import type { Transaction, TransactionWritePayload } from '@/modules/transactions/types';
import {
  AppDatePicker,
  AppInput,
  AppPercentageSplitEditor,
  AppSearchSelect,
  AppText,
  AppToggleButton,
} from '@/modules/shared/components';
import { useFormFieldInteraction } from '@/modules/shared/composables/useFormFieldInteraction';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const props = withDefaults(
  defineProps<{
    formId?: string;
    accountUsers?: ReadonlyArray<AccountMember>;
    financialGoals?: ReadonlyArray<{
      id: string;
      name: string;
      description?: string | null;
    }>;
    isLoadingFinancialGoals?: boolean;
    initialValues?: Partial<Transaction> | null;
    lockedAccountId?: string | null;
    serverError?: string | null;
  }>(),
  {
    formId: 'transaction-form',
    accountUsers: () => [],
    financialGoals: () => [],
    isLoadingFinancialGoals: false,
    initialValues: null,
    lockedAccountId: null,
    serverError: null,
  },
);

const emit = defineEmits<{
  submit: [payload: TransactionWritePayload];
  stateChange: [payload: FormState];
}>();

const transactionTypeOptions = [
  { value: 'income', label: 'Ingreso' },
  { value: 'expense', label: 'Egreso' },
] as const;

const transactionStatusOptions = [
  { value: 'pending', label: 'Pendiente' },
  { value: 'completed', label: 'Completado' },
] as const;

const {
  type,
  status,
  concept,
  amount,
  splitBetweenUsers,
  date,
  financialGoalId,
  userPayments,
  isSubmitting,
  isSubmitDisabled,
  meta,
  isIncome,
  isExpense,
  submitForm,
} = useTransactionForm({
  initialValues: () => props.initialValues,
  lockedAccountId: props.lockedAccountId,
});

const hasSharedAccount = computed(() => props.accountUsers.length > 1);
const showUserSplitToggle = computed(() => isExpense.value && hasSharedAccount.value);
const showUserSplitInputs = computed(() => showUserSplitToggle.value && splitBetweenUsers.value);
const { error: conceptError, touch: touchConcept } = useFormFieldInteraction('concept');
const { error: amountError, touch: touchAmount } = useFormFieldInteraction('amount');
const { error: userPaymentsError, touch: touchUserPayments } =
  useFormFieldInteraction('userPayments');
const { error: dateError, touch: touchDate } = useFormFieldInteraction('date');
const { error: financialGoalError, touch: touchFinancialGoal } =
  useFormFieldInteraction('financialGoalId');

const financialGoalOptions = computed(() =>
  props.financialGoals.map((goal) => ({
    value: goal.id,
    label: goal.name,
    description: goal.description ?? undefined,
  })),
);

watch(
  [isSubmitDisabled, isSubmitting, meta],
  () => {
    emit('stateChange', {
      canSubmit: !isSubmitDisabled.value,
      isSubmitting: isSubmitting.value,
    });
  },
  { immediate: true, deep: true },
);

watch(
  () => props.accountUsers,
  (nextUsers) => {
    if (Object.keys(userPayments.value).length > 0) {
      return;
    }

    userPayments.value = nextUsers.reduce<Record<string, number>>((accumulator, user) => {
      accumulator[user.id] = user.allocationPercentage ?? 0;
      return accumulator;
    }, {});
  },
  { immediate: true },
);

watch(showUserSplitToggle, (isVisible) => {
  if (!isVisible) {
    splitBetweenUsers.value = false;
  }
});

async function handleSubmit(): Promise<void> {
  const payload = await submitForm();

  if (!payload) {
    return;
  }

  emit('submit', payload);
}
</script>

<template>
  <form :id="props.formId" class="space-y-6" @submit.prevent="handleSubmit">
    <section
      v-if="props.serverError"
      class="rounded-xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText size="sm" class="text-(--app-color-danger)!">
        {{ props.serverError }}
      </AppText>
    </section>

    <section
      class="space-y-4 rounded-xl border bg-(--app-color-surface-muted) px-4 py-4"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <div class="flex items-center justify-between space-y-2">
        <label for="transaction-type" class="text-sm font-medium text-(--app-color-label)">
          Tipo
        </label>
        <AppToggleButton
          id="transaction-type"
          :model-value="type"
          :options="transactionTypeOptions"
          @update:model-value="type = $event"
        />
      </div>

      <div v-if="isIncome" class="flex items-center justify-between space-y-2">
        <label for="transaction-status" class="text-sm font-medium text-(--app-color-label)">
          Estatus
        </label>
        <AppToggleButton
          id="transaction-status"
          :model-value="status"
          :options="transactionStatusOptions"
          @update:model-value="status = $event"
        />
      </div>
    </section>

    <section class="space-y-5">
      <AppInput
        id="transaction-concept"
        v-model="concept"
        label="Concepto"
        placeholder="Ej. Pago a proveedor de logística"
        :error="conceptError"
        @blur="touchConcept"
        required
      />

      <div class="grid gap-4 sm:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
        <div class="space-y-4">
          <AppInput
            id="transaction-amount"
            v-model="amount"
            label="Cantidad"
            mask="amount"
            type="number"
            inputmode="decimal"
            min="0"
            step="0.01"
            placeholder="$ 00.00"
            :error="amountError"
            @blur="touchAmount"
            required
          />

          <section
            v-if="showUserSplitToggle"
            class="space-y-4 rounded-xl border px-4 py-4"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <label class="flex items-start gap-3">
              <input
                v-model="splitBetweenUsers"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-(--app-color-input-border) text-(--app-color-primary) focus:ring-(--app-color-focus-ring)"
                @change="touchUserPayments"
              />
              <div class="space-y-1">
                <span class="block text-sm font-medium text-(--app-color-label)">
                  Dividir entre usuarios de la cuenta
                </span>
                <AppText size="sm">
                  Usa los porcentajes del pivote `account_user` como base y ajústalos si hace falta.
                </AppText>
              </div>
            </label>

            <AppPercentageSplitEditor
              v-if="showUserSplitInputs"
              :users="props.accountUsers"
              :model-value="userPayments"
              @update:model-value="
                touchUserPayments();
                userPayments = $event;
              "
            />

            <p v-if="userPaymentsError" class="text-sm text-(--app-color-danger)">
              {{ userPaymentsError }}
            </p>
          </section>
        </div>

        <AppDatePicker
          id="transaction-date"
          v-model="date"
          label="Fecha"
          placeholder="AAAA-MM-DD"
          :error="dateError"
          @change="touchDate"
          @blur="touchDate"
          required
        />
      </div>
    </section>

    <section v-if="isIncome" class="space-y-3">
      <AppSearchSelect
        id="transaction-financial-goal"
        v-model="financialGoalId"
        label="Meta financiera"
        :options="financialGoalOptions"
        :disabled="props.isLoadingFinancialGoals"
        open-direction="top"
        :placeholder="
          props.isLoadingFinancialGoals ? 'Cargando metas financieras...' : 'Sin meta financiera'
        "
        search-placeholder="Buscar meta financiera"
        :empty-message="
          props.isLoadingFinancialGoals
            ? 'Cargando metas financieras...'
            : 'No encontramos metas con ese criterio.'
        "
        :error="financialGoalError"
        @change="touchFinancialGoal"
        @blur="touchFinancialGoal"
      />
    </section>
  </form>
</template>
