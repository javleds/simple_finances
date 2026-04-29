<script setup lang="ts">
import {
  CheckCircleIcon,
  MagnifyingGlassIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

import { AppInput, AppText, AppToggleButton } from '@/modules/shared/components';

type TransactionType = 'income' | 'expense';
type TransactionIncomeStatus = 'pending' | 'completed';

type TransactionAccountUser = {
  id: string;
  name: string;
  allocationPercentage?: number | null;
};

type TransactionFinancialGoal = {
  id: string;
  name: string;
  description?: string;
};

type TransactionFormSubmit = {
  type: TransactionType;
  status: TransactionIncomeStatus | null;
  concept: string;
  amount: number | null;
  date: string;
  splitBetweenUsers: boolean;
  financialGoalId: string | null;
  userPercentages: Record<string, number>;
};

const props = withDefaults(
  defineProps<{
    formId?: string;
    accountUsers?: ReadonlyArray<TransactionAccountUser>;
    financialGoals?: ReadonlyArray<TransactionFinancialGoal>;
  }>(),
  {
    formId: 'transaction-form',
    accountUsers: () => [],
    financialGoals: () => [],
  },
);

const emit = defineEmits<{
  submit: [payload: TransactionFormSubmit];
}>();

const transactionTypeOptions = [
  { value: 'income', label: 'Ingreso' },
  { value: 'expense', label: 'Egreso' },
] as const;

const transactionStatusOptions = [
  { value: 'pending', label: 'Pendiente' },
  { value: 'completed', label: 'Completado' },
] as const;

const today = new Date().toISOString().slice(0, 10);

const state = reactive({
  type: 'expense' as TransactionType,
  status: 'completed' as TransactionIncomeStatus,
  concept: '',
  amount: null as number | null,
  date: today,
  splitBetweenUsers: false,
  financialGoalId: null as string | null,
  userPercentages: {} as Record<string, number>,
});

const goalSearchTerm = ref('');
const goalSelectorRef = ref<HTMLElement | null>(null);

const isIncome = computed(() => state.type === 'income');
const isExpense = computed(() => state.type === 'expense');
const hasSharedAccount = computed(() => props.accountUsers.length > 1);
const showUserSplitToggle = computed(() => isExpense.value && hasSharedAccount.value);
const showUserSplitInputs = computed(() => showUserSplitToggle.value && state.splitBetweenUsers);

const filteredGoals = computed(() => {
  const normalizedQuery = goalSearchTerm.value.trim().toLowerCase();

  if (!normalizedQuery) {
    return props.financialGoals;
  }

  return props.financialGoals.filter((goal) => {
    const matchesName = goal.name.toLowerCase().includes(normalizedQuery);
    const matchesDescription = goal.description?.toLowerCase().includes(normalizedQuery) ?? false;

    return matchesName || matchesDescription;
  });
});

const selectedFinancialGoal = computed(() => {
  if (!state.financialGoalId) {
    return null;
  }

  return props.financialGoals.find((goal) => goal.id === state.financialGoalId) ?? null;
});

watch(
  () => state.type,
  (nextType) => {
    if (nextType === 'income') {
      state.status = 'completed';
      state.splitBetweenUsers = false;
      return;
    }

    if (!hasSharedAccount.value) {
      state.splitBetweenUsers = false;
    }
  },
  { immediate: true },
);

watch(
  () => props.accountUsers,
  (nextUsers) => {
    state.userPercentages = buildUserPercentages(nextUsers, state.userPercentages);

    if (nextUsers.length <= 1) {
      state.splitBetweenUsers = false;
    }
  },
  { immediate: true },
);

watch(showUserSplitToggle, (isVisible) => {
  if (!isVisible) {
    state.splitBetweenUsers = false;
  }
});

function buildUserPercentages(
  users: ReadonlyArray<TransactionAccountUser>,
  currentPercentages: Record<string, number>,
): Record<string, number> {
  return users.reduce<Record<string, number>>((percentages, user) => {
    const currentValue = currentPercentages[user.id];

    if (typeof currentValue === 'number' && Number.isFinite(currentValue)) {
      percentages[user.id] = currentValue;
      return percentages;
    }

    percentages[user.id] = normalizePercentage(user.allocationPercentage);
    return percentages;
  }, {});
}

function normalizePercentage(value: number | null | undefined): number {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return 0;
  }

  return value;
}

function updateAmount(event: Event): void {
  const nextValue = Number((event.target as HTMLInputElement).value);

  if (Number.isNaN(nextValue)) {
    state.amount = null;
    return;
  }

  state.amount = nextValue;
}

function updateUserPercentage(userId: string, event: Event): void {
  const nextValue = Number((event.target as HTMLInputElement).value);

  state.userPercentages[userId] = Number.isNaN(nextValue) ? 0 : nextValue;
}

function selectFinancialGoal(goalId: string): void {
  state.financialGoalId = goalId;
}

function clearFinancialGoal(): void {
  state.financialGoalId = null;
  goalSearchTerm.value = '';
}

function submitForm(): void {
  emit('submit', {
    type: state.type,
    status: isIncome.value ? state.status : null,
    concept: state.concept.trim(),
    amount: state.amount,
    date: state.date,
    splitBetweenUsers: showUserSplitInputs.value,
    financialGoalId: state.financialGoalId,
    userPercentages: showUserSplitInputs.value ? { ...state.userPercentages } : {},
  });
}

function handleDocumentPointerDown(event: PointerEvent): void {
  if (!goalSelectorRef.value) {
    return;
  }

  if (goalSelectorRef.value.contains(event.target as Node)) {
    return;
  }

  goalSearchTerm.value = '';
}

if (typeof document !== 'undefined') {
  document.addEventListener('pointerdown', handleDocumentPointerDown);
}

onBeforeUnmount(() => {
  if (typeof document === 'undefined') {
    return;
  }

  document.removeEventListener('pointerdown', handleDocumentPointerDown);
});
</script>

<template>
  <form :id="props.formId" class="space-y-6" @submit.prevent="submitForm">
    <section
      class="space-y-4 rounded-xl border bg-[var(--app-color-surface-muted)] px-4 py-4"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <div class="space-y-2">
        <label for="transaction-type" class="text-sm font-medium text-[var(--app-color-label)]">
          Tipo
        </label>
        <AppToggleButton
          id="transaction-type"
          :model-value="state.type"
          :options="transactionTypeOptions"
          @update:model-value="state.type = $event as TransactionType"
        />
      </div>

      <div v-if="isIncome" class="space-y-2">
        <label for="transaction-status" class="text-sm font-medium text-[var(--app-color-label)]">
          Estatus
        </label>
        <AppToggleButton
          id="transaction-status"
          :model-value="state.status"
          :options="transactionStatusOptions"
          @update:model-value="state.status = $event as TransactionIncomeStatus"
        />
      </div>
    </section>

    <section class="space-y-5">
      <AppInput
        id="transaction-concept"
        v-model="state.concept"
        label="Concepto"
        placeholder="Ej. Pago a proveedor de logística"
        required
      />

      <div class="grid gap-4 sm:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
        <div class="space-y-4">
          <AppInput
            id="transaction-amount"
            :model-value="state.amount ?? ''"
            label="Cantidad"
            type="number"
            inputmode="decimal"
            min="0"
            step="0.01"
            placeholder="0.00"
            required
            @input="updateAmount"
          />

          <section
            v-if="showUserSplitToggle"
            class="space-y-4 rounded-xl border px-4 py-4"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <label class="flex items-start gap-3">
              <input
                v-model="state.splitBetweenUsers"
                type="checkbox"
                class="mt-1 h-4 w-4 rounded border-[var(--app-color-input-border)] text-[var(--app-color-primary)] focus:ring-[var(--app-color-focus-ring)]"
              />
              <div class="space-y-1">
                <span class="block text-sm font-medium text-[var(--app-color-label)]">
                  Dividir entre usuarios de la cuenta
                </span>
                <AppText size="sm">
                  Usa los porcentajes definidos por usuario o ajústalos manualmente para este egreso.
                </AppText>
              </div>
            </label>

            <div v-if="showUserSplitInputs" class="grid gap-4 sm:grid-cols-2">
              <AppInput
                v-for="user in props.accountUsers"
                :id="`transaction-user-percentage-${user.id}`"
                :key="user.id"
                :model-value="state.userPercentages[user.id] ?? 0"
                :label="`Porcentaje de ${user.name}`"
                type="number"
                inputmode="decimal"
                min="0"
                step="0.01"
                placeholder="0"
                @input="updateUserPercentage(user.id, $event)"
              />
            </div>
          </section>
        </div>

        <AppInput
          id="transaction-date"
          v-model="state.date"
          label="Fecha"
          type="date"
          required
        />
      </div>
    </section>

    <section class="space-y-3" ref="goalSelectorRef">
      <div class="flex items-center justify-between gap-3">
        <label
          for="transaction-financial-goal-search"
          class="text-sm font-medium text-[var(--app-color-label)]"
        >
          Meta financiera
        </label>

        <button
          v-if="selectedFinancialGoal"
          type="button"
          class="text-sm font-medium text-[var(--app-color-link)] transition hover:text-[var(--app-color-link-hover)]"
          @click="clearFinancialGoal"
        >
          Limpiar
        </button>
      </div>

      <div
        class="space-y-3 rounded-xl border bg-[var(--app-color-surface-muted)] px-4 py-4"
        :style="{ borderColor: 'var(--app-color-border)' }"
      >
        <div v-if="selectedFinancialGoal" class="flex items-start gap-3 rounded-lg bg-[var(--app-color-surface)] px-3 py-3">
          <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-[var(--app-color-success)]" />
          <div class="min-w-0 space-y-1">
            <p class="text-sm font-semibold text-[var(--app-color-text)]">
              {{ selectedFinancialGoal.name }}
            </p>
            <AppText v-if="selectedFinancialGoal.description" size="sm">
              {{ selectedFinancialGoal.description }}
            </AppText>
          </div>
        </div>

        <div class="relative">
          <div
            class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[var(--app-color-text-subtle)]"
          >
            <MagnifyingGlassIcon class="h-5 w-5" />
          </div>
          <AppInput
            id="transaction-financial-goal-search"
            v-model="goalSearchTerm"
            class="pl-11"
            placeholder="Buscar meta financiera"
          />
        </div>

        <div
          v-if="props.financialGoals.length > 0"
          class="max-h-48 space-y-2 overflow-y-auto pr-1"
        >
          <button
            v-for="goal in filteredGoals"
            :key="goal.id"
            type="button"
            class="w-full rounded-lg border px-4 py-3 text-left transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              state.financialGoalId === goal.id
                ? 'bg-[var(--app-color-surface)] text-[var(--app-color-text)]'
                : 'bg-transparent text-[var(--app-color-text-subtle)] hover:bg-[var(--app-color-surface)] hover:text-[var(--app-color-text)]'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="selectFinancialGoal(goal.id)"
          >
            <div class="space-y-1">
              <p class="text-sm font-semibold">{{ goal.name }}</p>
              <AppText v-if="goal.description" size="sm">
                {{ goal.description }}
              </AppText>
            </div>
          </button>

          <div
            v-if="filteredGoals.length === 0"
            class="rounded-lg border border-dashed px-4 py-4 text-center"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm">
              No encontramos metas con ese criterio. Puedes guardar la transacción sin asociarla.
            </AppText>
          </div>
        </div>

        <div
          v-else
          class="rounded-lg border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <UserGroupIcon class="mx-auto h-6 w-6 text-[var(--app-color-text-subtle)]" />
          <AppText size="sm" class="mt-2">
            Esta cuenta aún no tiene metas financieras disponibles.
          </AppText>
        </div>
      </div>
    </section>
  </form>
</template>
