<script setup lang="ts">
import { computed, watch } from 'vue';
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
import { useAccountGoalModalActions } from '@/modules/accounts/composables/useAccountGoalModalActions';
import { useAccountGoalModals } from '@/modules/accounts/composables/useAccountGoalModals';
import { useAccountGoalPageActions } from '@/modules/accounts/composables/useAccountGoalPageActions';
import { useAccountGoalPresentation } from '@/modules/accounts/composables/useAccountGoalPresentation';
import { useAccountGoalsCrud } from '@/modules/accounts/composables/useAccountGoalsCrud';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import {
  AppButton,
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';

const route = useRoute();
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

const {
  closeCreateGoal,
  closeDeleteGoal,
  closeEditGoal,
  closeFilters,
  createFormState,
  editFormState,
  handleCreateFormStateChange,
  handleEditFormStateChange,
  isCreateGoalOpen,
  isDeleteGoalOpen,
  isEditGoalOpen,
  isFiltersOpen,
  openCreateGoal,
  openDeleteGoal,
  openEditGoal,
  openFilters,
  selectedGoal,
} = useAccountGoalModals({
  goals,
  clearDeleteError,
  clearSaveError,
});

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
const { filteredGoalItems, infiniteStatusLabel } = useAccountGoalPresentation({
  goals,
  hasReachedEnd,
  isLoadingMore,
  selectedStatuses,
});

const { createGoalActions, deleteGoalActions, editGoalActions } = useAccountGoalModalActions({
  createFormState,
  editFormState,
  isDeleting,
  isSaving,
  selectedGoal,
});
const {
  confirmDeleteGoal,
  handleCreateGoalSubmit,
  handleEditGoalSubmit,
  handleLoadMoreRetry,
  reloadGoals,
} = useAccountGoalPageActions({
  accountId,
  activeFilters,
  closeCreateGoal,
  closeDeleteGoal,
  closeEditGoal,
  createGoal,
  deleteGoal,
  goalsPerPage,
  loadGoals,
  loadMoreGoals,
  selectedGoal,
  updateGoal,
});

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
