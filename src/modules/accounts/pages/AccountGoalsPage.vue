<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

import AccountGoalForm from '@/modules/accounts/components/AccountGoalForm.vue';
import AccountGoalListItem from '@/modules/accounts/components/AccountGoalListItem.vue';
import { useAccountGoalsCrud } from '@/modules/accounts/composables/useAccountGoalsCrud';
import type { AccountGoalWritePayload } from '@/modules/accounts/schemas/accountGoalSchemas';
import {
  AppButton,
  AppCard,
  AppIconButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type GoalStatus = 'on-track' | 'at-risk' | 'completed';
type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const route = useRoute();
const searchTerm = ref('');
const isFiltersOpen = ref(false);
const isCreateGoalOpen = ref(false);
const isEditGoalOpen = ref(false);
const isDeleteGoalOpen = ref(false);
const selectedStatuses = ref<GoalStatus[]>([]);
const selectedGoalId = ref<string | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });

const goalStatusOptions = [
  { value: 'on-track', label: 'En curso' },
  { value: 'at-risk', label: 'En riesgo' },
  { value: 'completed', label: 'Completada' },
] as const;

const accountId = computed(() =>
  typeof route.params.accountId === 'string' ? route.params.accountId : '',
);

const {
  goals,
  hasGoals,
  isLoading,
  isSaving,
  isDeleting,
  loadError,
  saveError,
  deleteError,
  clearSaveError,
  clearDeleteError,
  loadGoals,
  createGoal,
  updateGoal,
  deleteGoal,
} = useAccountGoalsCrud();

const filteredGoalItems = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return goals.value.filter((goal) => {
    const matchesQuery =
      normalizedQuery.length === 0 || goal.name.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    const status = goal.status === 'completed' ? 'completed' : resolveGoalStatus(goal.progress);

    if (selectedStatuses.value.length > 0 && !selectedStatuses.value.includes(status)) {
      return false;
    }

    return true;
  });
});

const selectedGoal = computed(() => {
  if (!selectedGoalId.value) {
    return null;
  }

  return goals.value.find((goal) => goal.id === selectedGoalId.value) ?? null;
});

const createGoalActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-goal',
    label: isSaving.value ? 'Guardando...' : 'Crear meta',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'account-goal-form',
    disabled: !createFormState.value.canSubmit || isSaving.value,
  },
]);

const editGoalActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-edit-goal',
    label: isSaving.value ? 'Guardando...' : 'Guardar cambios',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'edit-account-goal-form',
    disabled: !editFormState.value.canSubmit || isSaving.value,
  },
]);

const deleteGoalActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-delete-goal',
    label: isDeleting.value ? 'Eliminando...' : 'Eliminar meta',
    tone: 'primary' as const,
    disabled: !selectedGoal.value || isDeleting.value,
  },
]);

onMounted(() => {
  if (accountId.value) {
    void loadGoals(accountId.value);
  }
});

function resolveGoalStatus(progress: number): GoalStatus {
  if (progress >= 100) {
    return 'completed';
  }

  if (progress < 50) {
    return 'at-risk';
  }

  return 'on-track';
}

function formatDateLabel(date: string | null): string {
  if (!date) {
    return 'Sin fecha límite';
  }

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${date}T00:00:00`));
}

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
}

function toggleStatus(status: GoalStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function handleFiltersModalAction(actionKey: string): void {
  if (actionKey === 'clear') {
    clearFilters();
    return;
  }

  if (actionKey === 'close') {
    closeFilters();
  }
}

function openCreateGoal(): void {
  clearSaveError();
  createFormState.value = { canSubmit: false, isSubmitting: false };
  isCreateGoalOpen.value = true;
}

function closeCreateGoal(): void {
  isCreateGoalOpen.value = false;
  clearSaveError();
}

function openEditGoal(goalId: string): void {
  clearSaveError();
  selectedGoalId.value = goalId;
  editFormState.value = { canSubmit: false, isSubmitting: false };
  isEditGoalOpen.value = true;
}

function closeEditGoal(): void {
  isEditGoalOpen.value = false;
  selectedGoalId.value = null;
  clearSaveError();
}

function openDeleteGoal(goalId: string): void {
  clearDeleteError();
  selectedGoalId.value = goalId;
  isDeleteGoalOpen.value = true;
}

function closeDeleteGoal(): void {
  isDeleteGoalOpen.value = false;
  selectedGoalId.value = null;
  clearDeleteError();
}

async function handleCreateGoalSubmit(payload: AccountGoalWritePayload): Promise<void> {
  const wasCreated = await createGoal(payload);

  if (wasCreated) {
    closeCreateGoal();
  }
}

async function handleEditGoalSubmit(payload: AccountGoalWritePayload): Promise<void> {
  if (!selectedGoal.value) {
    return;
  }

  const wasUpdated = await updateGoal(selectedGoal.value.id, payload);

  if (wasUpdated) {
    closeEditGoal();
  }
}

async function confirmDeleteGoal(): Promise<void> {
  if (!selectedGoal.value) {
    return;
  }

  const wasDeleted = await deleteGoal(selectedGoal.value.id, accountId.value);

  if (wasDeleted) {
    closeDeleteGoal();
  }
}

function handleCreateFormStateChange(state: FormState): void {
  createFormState.value = state;
}

function handleEditFormStateChange(state: FormState): void {
  editFormState.value = state;
}
</script>

<template>
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Metas financieras</AppTitle>
          <AppText>Cada meta vive dentro de la cuenta y comparte su mismo contexto.</AppText>
        </div>

        <AppButton variant="primary" @click="openCreateGoal">
          <PlusIcon class="h-4 w-4" />
        </AppButton>
      </div>
    </AppCard>

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
        >
          <MagnifyingGlassIcon class="h-5 w-5" />
        </div>
        <AppInput
          id="goal-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar meta por nombre"
          class="pl-11"
        />
      </div>

      <AppIconButton ariaLabel="Abrir filtros avanzados" @click="openFilters">
        <AdjustmentsHorizontalIcon class="h-5 w-5" />
      </AppIconButton>
    </div>

    <section
      v-if="loadError && hasGoals"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <section v-if="isLoading && !hasGoals" class="rounded-2xl border px-4 py-10 text-center">
      <AppText>Cargando metas...</AppText>
    </section>

    <section v-else class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">{{ filteredGoalItems.length }} metas visibles</AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <AccountGoalListItem
          v-for="goal in filteredGoalItems"
          :key="goal.id"
          :current-amount="goal.amount * (goal.progress / 100)"
          :deadline-label="formatDateLabel(goal.deadline)"
          :item-id="goal.id"
          :owner-label="goal.status === 'completed' ? 'Meta completada' : 'Meta en progreso'"
          :progress="goal.progress"
          :remaining-amount="Math.max(goal.amount - goal.amount * (goal.progress / 100), 0)"
          :status="goal.status === 'completed' ? 'completed' : resolveGoalStatus(goal.progress)"
          :target-amount="goal.amount"
          :title="goal.name"
          @delete="openDeleteGoal"
          @edit="openEditGoal"
        />

        <div
          v-if="filteredGoalItems.length === 0"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm"
            >No hay metas que coincidan con la búsqueda o los filtros actuales.</AppText
          >
        </div>

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm"
            >Sigue desplazándote para revisar más metas conforme la cuenta acumule
            objetivos.</AppText
          >
        </div>
      </div>
    </section>

    <AppModal
      :open="isFiltersOpen"
      :actions="[
        { key: 'clear', label: 'Limpiar filtros', tone: 'neutral', icon: ArrowPathIcon },
        { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
      ]"
      title="Filtros avanzados"
      variant="default"
      @action="handleFiltersModalAction"
      @close="closeFilters"
    >
      <div class="space-y-5">
        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Estatus</AppTitle>
          <AppText>Filtra metas según su nivel de avance.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in goalStatusOptions"
            :key="status.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              selectedStatuses.includes(status.value)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleStatus(status.value)"
          >
            {{ status.label }}
          </button>
        </div>
      </div>
    </AppModal>

    <AppModal
      :open="isCreateGoalOpen"
      :actions="createGoalActions"
      title="Crear meta"
      variant="default"
      @close="closeCreateGoal"
    >
      <AccountGoalForm
        v-if="accountId"
        form-id="account-goal-form"
        :account-id="accountId"
        :server-error="saveError"
        @state-change="handleCreateFormStateChange"
        @submit="handleCreateGoalSubmit"
      />
    </AppModal>

    <AppModal
      :open="isEditGoalOpen"
      :actions="editGoalActions"
      title="Editar meta"
      variant="default"
      @close="closeEditGoal"
    >
      <AccountGoalForm
        v-if="accountId && selectedGoal"
        form-id="edit-account-goal-form"
        :account-id="accountId"
        :initial-values="selectedGoal"
        :server-error="saveError"
        @state-change="handleEditFormStateChange"
        @submit="handleEditGoalSubmit"
      />
    </AppModal>

    <AppModal
      :open="isDeleteGoalOpen"
      :actions="deleteGoalActions"
      title="Eliminar meta"
      variant="danger"
      @action="$event === 'confirm-delete-goal' && confirmDeleteGoal()"
      @close="closeDeleteGoal"
    >
      <div class="space-y-3">
        <AppText v-if="selectedGoal">
          Vas a eliminar
          <strong>{{ selectedGoal.name }}</strong
          >.
        </AppText>
        <AppText v-if="deleteError" class="text-(--app-color-danger)!">{{ deleteError }}</AppText>
      </div>
    </AppModal>
  </section>
</template>
