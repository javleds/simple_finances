<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import DistributionRuleDeleteModal from '@/modules/distribution/components/DistributionRuleDeleteModal.vue';
import DistributionRuleFiltersModal from '@/modules/distribution/components/DistributionRuleFiltersModal.vue';
import DistributionRuleFormModal from '@/modules/distribution/components/DistributionRuleFormModal.vue';
import DistributionRulesList from '@/modules/distribution/components/DistributionRulesList.vue';
import DistributionRulesToolbar from '@/modules/distribution/components/DistributionRulesToolbar.vue';
import { useDistributionRuleFilters } from '@/modules/distribution/composables/useDistributionRuleFilters';
import { useDistributionRuleModalActions } from '@/modules/distribution/composables/useDistributionRuleModalActions';
import { useDistributionRuleModals } from '@/modules/distribution/composables/useDistributionRuleModals';
import { useDistributionRulesCrud } from '@/modules/distribution/composables/useDistributionRulesCrud';
import type {
  DistributionFrequency,
  DistributionRuleWritePayload,
} from '@/modules/distribution/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import {
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const route = useRoute();

const frequencyOptions = [
  { value: 'monthly', label: 'Mensual' },
  { value: 'semi_monthly', label: 'Quincenal' },
] as const;
const defaultRulesPerPage = 20;
const {
  activeFilters,
  clearFilters,
  searchTerm,
  selectedFrequencies,
  toggleFrequency,
} = useDistributionRuleFilters();

const {
  rules,
  hasRules,
  hasMoreRules,
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
  loadRules,
  loadMoreRules,
  createRule,
  updateRule,
  deleteRule,
} = useDistributionRulesCrud();

const {
  closeCreateRule,
  closeDeleteRule,
  closeEditRule,
  closeFilters,
  createFormState,
  editFormState,
  handleCreateFormStateChange,
  handleEditFormStateChange,
  isCreateRuleOpen,
  isDeleteRuleOpen,
  isEditRuleOpen,
  isFiltersOpen,
  openCreateRule,
  openDeleteRule,
  openEditRule,
  openFilters,
  selectedRule,
} = useDistributionRuleModals({
  clearDeleteError,
  clearSaveError,
  rules,
});

const { createRuleActions, deleteRuleActions, editRuleActions } =
  useDistributionRuleModalActions({
    createFormState,
    editFormState,
    isDeleting,
    isSaving,
    selectedRule,
  });

const rulesPerPage = computed(() => {
  const rawValue = typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultRulesPerPage;
  }

  return rawValue;
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreRules.value),
  onIntersect: () => {
    void loadMoreRules();
  },
});

watch(
  [activeFilters, rulesPerPage],
  ([nextFilters, nextPerPage]) => {
    void loadRules(nextFilters, {
      reset: true,
      perPage: nextPerPage,
    });
  },
  { immediate: true },
);

async function handleCreateRuleSubmit(payload: DistributionRuleWritePayload): Promise<void> {
  const wasCreated = await createRule(payload);

  if (wasCreated) {
    closeCreateRule();
  }
}

async function handleEditRuleSubmit(payload: DistributionRuleWritePayload): Promise<void> {
  if (!selectedRule.value) {
    return;
  }

  const wasUpdated = await updateRule(selectedRule.value.id, payload);

  if (wasUpdated) {
    closeEditRule();
  }
}

async function confirmDeleteRule(): Promise<void> {
  if (!selectedRule.value) {
    return;
  }

  const wasDeleted = await deleteRule(selectedRule.value.id);

  if (wasDeleted) {
    closeDeleteRule();
  }
}

function reloadRules(): void {
  void loadRules(activeFilters.value, {
    reset: true,
    perPage: rulesPerPage.value,
  });
}

function handleLoadMoreRetry(): void {
  void loadMoreRules();
}

function infiniteStatusLabel(): string {
  if (isLoadingMore.value) {
    return 'Cargando más reglas...';
  }

  if (hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para revisar más reglas conforme crezca la facility.';
}
</script>

<template>
  <div class="space-y-5">
    <DistributionRulesToolbar
      v-model:search-term="searchTerm"
      @create="openCreateRule"
      @open-filters="openFilters"
    />

    <section
      v-if="loadError && hasRules"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <AppListState
      :error="loadError"
      :has-items="hasRules"
      :is-loading="isLoading"
      loading-label="Cargando reglas..."
      @retry="reloadRules"
    >
      <DistributionRulesList :rules="rules" @delete="openDeleteRule" @edit="openEditRule">
        <template #footer>
          <div ref="loadMoreSentinel">
            <AppLoadMoreFooter
              :label="infiniteStatusLabel()"
              :show-retry="Boolean(loadError && hasRules)"
              @retry="handleLoadMoreRetry"
            />
          </div>
        </template>
      </DistributionRulesList>
    </AppListState>

    <DistributionRuleFiltersModal
      :open="isFiltersOpen"
      :frequency-options="frequencyOptions"
      :selected-frequencies="selectedFrequencies"
      @clear="clearFilters"
      @close="closeFilters"
      @toggle-frequency="toggleFrequency"
    />

    <DistributionRuleFormModal
      :open="isCreateRuleOpen"
      :actions="createRuleActions"
      form-id="distribution-rule-form"
      :server-error="saveError"
      title="Nueva regla"
      @close="closeCreateRule"
      @state-change="handleCreateFormStateChange"
      @submit="handleCreateRuleSubmit"
    />

    <DistributionRuleFormModal
      :open="isEditRuleOpen"
      :actions="editRuleActions"
      form-id="edit-distribution-rule-form"
      :initial-values="selectedRule"
      requires-initial-values
      :server-error="saveError"
      title="Editar regla"
      @close="closeEditRule"
      @state-change="handleEditFormStateChange"
      @submit="handleEditRuleSubmit"
    />

    <DistributionRuleDeleteModal
      :open="isDeleteRuleOpen"
      :actions="deleteRuleActions"
      :delete-error="deleteError"
      :rule="selectedRule"
      @close="closeDeleteRule"
      @confirm="confirmDeleteRule"
    />
  </div>
</template>
