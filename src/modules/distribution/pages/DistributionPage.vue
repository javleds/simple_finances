<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import DistributionRuleDeleteModal from '@/modules/distribution/components/DistributionRuleDeleteModal.vue';
import DistributionRuleFiltersModal from '@/modules/distribution/components/DistributionRuleFiltersModal.vue';
import DistributionRuleFormModal from '@/modules/distribution/components/DistributionRuleFormModal.vue';
import DistributionRulesList from '@/modules/distribution/components/DistributionRulesList.vue';
import DistributionRulesToolbar from '@/modules/distribution/components/DistributionRulesToolbar.vue';
import { useDistributionRulesCrud } from '@/modules/distribution/composables/useDistributionRulesCrud';
import type {
  DistributionFrequency,
  DistributionRuleListFilters,
  DistributionRuleWritePayload,
} from '@/modules/distribution/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';
import {
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const searchTerm = ref('');
const route = useRoute();
const router = useRouter();
const selectedFrequencies = ref<DistributionFrequency[]>([]);
const isCreateRuleOpen = ref(false);
const isEditRuleOpen = ref(false);
const isDeleteRuleOpen = ref(false);
const isFiltersOpen = ref(false);
const selectedRuleId = ref<string | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });

const frequencyOptions = [
  { value: 'monthly', label: 'Mensual' },
  { value: 'semi_monthly', label: 'Quincenal' },
] as const;
const defaultRulesPerPage = 20;

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

const activeFilters = computed<DistributionRuleListFilters>(() => ({
  search: searchTerm.value.trim() || undefined,
  frequency: selectedFrequencies.value.length > 0 ? [...selectedFrequencies.value] : undefined,
}));

const selectedRule = computed(() => {
  if (!selectedRuleId.value) {
    return null;
  }

  return rules.value.find((rule) => rule.id === selectedRuleId.value) ?? null;
});

const createRuleActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-rule',
    label: isSaving.value ? 'Guardando...' : 'Crear regla',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'distribution-rule-form',
    disabled: !createFormState.value.canSubmit || isSaving.value,
  },
]);

const editRuleActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-edit-rule',
    label: isSaving.value ? 'Guardando...' : 'Guardar cambios',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'edit-distribution-rule-form',
    disabled: !editFormState.value.canSubmit || isSaving.value,
  },
]);

const deleteRuleActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-delete-rule',
    label: isDeleting.value ? 'Eliminando...' : 'Eliminar regla',
    tone: 'primary' as const,
    disabled: !selectedRule.value || isDeleting.value,
  },
]);

watch(
  () => route.query,
  (nextQuery) => {
    searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
    selectedFrequencies.value = parseQueryValues(nextQuery.frequency, isDistributionFrequency);
  },
  { immediate: true },
);

watch(
  [searchTerm, selectedFrequencies],
  () => {
    const nextQuery = {
      ...route.query,
      search: searchTerm.value.trim() || undefined,
      frequency:
        selectedFrequencies.value.length > 0 ? selectedFrequencies.value.join(',') : undefined,
    };

    if (areQueriesEqual(route.query, nextQuery)) {
      return;
    }

    void router.replace({ query: nextQuery });
  },
  { deep: true },
);

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

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedFrequencies.value = [];
}

function toggleFrequency(frequency: DistributionFrequency): void {
  if (selectedFrequencies.value.includes(frequency)) {
    selectedFrequencies.value = selectedFrequencies.value.filter((item) => item !== frequency);
    return;
  }

  selectedFrequencies.value = [...selectedFrequencies.value, frequency];
}

function openCreateRule(): void {
  clearSaveError();
  createFormState.value = { canSubmit: false, isSubmitting: false };
  isCreateRuleOpen.value = true;
}

function closeCreateRule(): void {
  isCreateRuleOpen.value = false;
  clearSaveError();
}

function openEditRule(ruleId: string): void {
  clearSaveError();
  selectedRuleId.value = ruleId;
  editFormState.value = { canSubmit: false, isSubmitting: false };
  isEditRuleOpen.value = true;
}

function closeEditRule(): void {
  isEditRuleOpen.value = false;
  selectedRuleId.value = null;
  clearSaveError();
}

function openDeleteRule(ruleId: string): void {
  clearDeleteError();
  selectedRuleId.value = ruleId;
  isDeleteRuleOpen.value = true;
}

function closeDeleteRule(): void {
  isDeleteRuleOpen.value = false;
  selectedRuleId.value = null;
  clearDeleteError();
}

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

function handleCreateFormStateChange(state: FormState): void {
  createFormState.value = state;
}

function handleEditFormStateChange(state: FormState): void {
  editFormState.value = state;
}

function reloadRules(): void {
  void loadRules(activeFilters.value, {
    reset: true,
    perPage: rulesPerPage.value,
  });
}

function isDistributionFrequency(value: string): value is DistributionFrequency {
  return value === 'monthly' || value === 'semi_monthly';
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
