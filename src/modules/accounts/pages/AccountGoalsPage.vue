<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import AccountGoalDeleteModal from '@/modules/accounts/components/AccountGoalDeleteModal.vue';
import AccountGoalFiltersModal from '@/modules/accounts/components/AccountGoalFiltersModal.vue';
import AccountGoalFormModal from '@/modules/accounts/components/AccountGoalFormModal.vue';
import AccountGoalsList from '@/modules/accounts/components/AccountGoalsList.vue';
import AccountGoalsToolbar from '@/modules/accounts/components/AccountGoalsToolbar.vue';
import {
  useAccountGoalFilters,
  type AccountGoalStatusFilter,
} from '@/modules/accounts/composables/useAccountGoalFilters';
import { useAccountGoalsCrud } from '@/modules/accounts/composables/useAccountGoalsCrud';
import type { AccountGoalWritePayload } from '@/modules/accounts/schemas/accountGoalSchemas';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import {
  AppButton,
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const route = useRoute();
const isFiltersOpen = ref(false);
const isCreateGoalOpen = ref(false);
const isEditGoalOpen = ref(false);
const isDeleteGoalOpen = ref(false);
const selectedGoalId = ref<string | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const { activeFilters, clearFilters, searchTerm, selectedStatuses, toggleStatus } =
  useAccountGoalFilters();

const goalStatusOptions = [
  { value: 'on-track', label: 'En curso' },
  { value: 'at-risk', label: 'En riesgo' },
  { value: 'completed', label: 'Completada' },
] as const;
const defaultGoalsPerPage = 20;

const accountId = computed(() =>
  typeof route.params.accountId === 'string' ? route.params.accountId : '',
);

const {
  goals,
  hasGoals,
  hasMoreGoals,
  hasReachedEnd,
  isLoading,
  isLoadingMore,
  isSaving,
  isDeleting,
  loadError,
  saveError,
  deleteError,
  clearSaveError,
  clearDeleteError,
  loadGoals,
  loadMoreGoals,
  createGoal,
  updateGoal,
  deleteGoal,
} = useAccountGoalsCrud();

const goalsPerPage = computed(() => {
  const rawValue = typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultGoalsPerPage;
  }

  return rawValue;
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreGoals.value),
  onIntersect: () => {
    void loadMoreGoals();
  },
});

const filteredGoalItems = computed(() => {
  return goals.value.filter((goal) => {
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

watch(
  [accountId, activeFilters, goalsPerPage],
  ([nextAccountId, nextFilters, nextPerPage]) => {
    if (!nextAccountId) {
      return;
    }

    void loadGoals(nextAccountId, nextFilters, {
      reset: true,
      perPage: nextPerPage,
    });
  },
  { immediate: true },
);

function resolveGoalStatus(progress: number): AccountGoalStatusFilter {
  if (progress >= 100) {
    return 'completed';
  }

  if (progress < 50) {
    return 'at-risk';
  }

  return 'on-track';
}

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
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

function reloadGoals(): void {
  if (!accountId.value) {
    return;
  }

  void loadGoals(accountId.value, activeFilters.value, {
    reset: true,
    perPage: goalsPerPage.value,
  });
}

function handleLoadMoreRetry(): void {
  void loadMoreGoals();
}

function infiniteStatusLabel(): string {
  if (isLoadingMore.value) {
    return 'Cargando más metas...';
  }

  if (hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para revisar más metas conforme la cuenta acumule objetivos.';
}
</script>

<template>
  <section class="space-y-4">
    <AccountGoalsToolbar
      v-model:search-term="searchTerm"
      @create="openCreateGoal"
      @open-filters="openFilters"
    />

    <section
      v-if="loadError && hasGoals"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <AppListState
      :error="loadError"
      :has-items="hasGoals"
      :is-loading="isLoading"
      loading-label="Cargando metas..."
      @retry="reloadGoals"
    >
      <AccountGoalsList
        :goals="filteredGoalItems"
        @delete="openDeleteGoal"
        @edit="openEditGoal"
      >
        <template #footer>
          <div ref="loadMoreSentinel">
            <AppLoadMoreFooter
              :label="infiniteStatusLabel()"
              :show-retry="Boolean(loadError && hasGoals)"
              @retry="handleLoadMoreRetry"
            />
          </div>
        </template>
      </AccountGoalsList>
    </AppListState>

    <AccountGoalFiltersModal
      :open="isFiltersOpen"
      :options="goalStatusOptions"
      :selected-statuses="selectedStatuses"
      @clear="clearFilters"
      @close="closeFilters"
      @toggle-status="toggleStatus($event as AccountGoalStatusFilter)"
    />

    <AccountGoalFormModal
      :open="isCreateGoalOpen"
      :account-id="accountId"
      :actions="createGoalActions"
      form-id="account-goal-form"
      :server-error="saveError"
      title="Crear meta"
      @close="closeCreateGoal"
      @state-change="handleCreateFormStateChange"
      @submit="handleCreateGoalSubmit"
    />

    <AccountGoalFormModal
      :open="isEditGoalOpen"
      :account-id="accountId"
      :actions="editGoalActions"
      form-id="edit-account-goal-form"
      :initial-values="selectedGoal"
      :server-error="saveError"
      title="Editar meta"
      @close="closeEditGoal"
      @state-change="handleEditFormStateChange"
      @submit="handleEditGoalSubmit"
    />

    <AccountGoalDeleteModal
      :open="isDeleteGoalOpen"
      :actions="deleteGoalActions"
      :delete-error="deleteError"
      :goal="selectedGoal"
      @close="closeDeleteGoal"
      @confirm="confirmDeleteGoal"
    />
  </section>
</template>
