<script setup lang="ts">
import { ArrowPathIcon, MagnifyingGlassIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import DistributionRuleForm from '@/modules/distribution/components/DistributionRuleForm.vue';
import DistributionRuleListItem from '@/modules/distribution/components/DistributionRuleListItem.vue';
import { useDistributionRulesCrud } from '@/modules/distribution/composables/useDistributionRulesCrud';
import { formatDistributionFrequency } from '@/modules/distribution/schemas/distributionSchemas';
import type {
  DistributionFrequency,
  DistributionRuleListFilters,
  DistributionRuleWritePayload,
} from '@/modules/distribution/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';
import {
  AppButton,
  AppInput,
  AppModal,
  AppSectionBar,
  AppText,
  AppTitle,
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

function handleFiltersModalAction(actionKey: string): void {
  if (actionKey === 'clear') {
    clearFilters();
    return;
  }

  if (actionKey === 'close') {
    closeFilters();
  }
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
    <AppSectionBar
      title="Ingresos fijos"
      description="Cada regla define un ingreso fijo y agrupa sus distribuciones asociadas."
    >
      <template #actions>
        <AppButton variant="primary" @click="openCreateRule">
          <PlusIcon class="h-4 w-4" />
        </AppButton>
      </template>
    </AppSectionBar>

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
        >
          <MagnifyingGlassIcon class="h-5 w-5" />
        </div>
        <AppInput
          id="distribution-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar regla por nombre"
          class="pl-11"
        />
      </div>

      <AppButton variant="secondary" @click="openFilters">Filtros</AppButton>
    </div>

    <section
      v-if="loadError && hasRules"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <section v-if="isLoading && !hasRules" class="rounded-2xl border px-4 py-10 text-center">
      <AppText>Cargando reglas...</AppText>
    </section>

    <section
      v-else-if="loadError && !hasRules"
      class="space-y-3 rounded-2xl border px-4 py-6 text-center"
    >
      <AppText>{{ loadError }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="secondary" @click="reloadRules">Reintentar</AppButton>
      </div>
    </section>

    <section v-else class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">{{ rules.length }} reglas visibles</AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <DistributionRuleListItem
          v-for="rule in rules"
          :key="rule.id"
          :frequency="rule.frequency"
          :item-id="rule.id"
          :name="rule.name"
          :outcomes-count="rule.outcomesCount"
          :total-amount="rule.totalAmount"
          @delete="openDeleteRule"
          @edit="openEditRule"
        />

        <div
          v-if="rules.length === 0"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm"
            >No hay reglas que coincidan con la búsqueda o filtros actuales.</AppText
          >
        </div>

        <div
          ref="loadMoreSentinel"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">{{ infiniteStatusLabel() }}</AppText>
          <div v-if="loadError && hasRules" class="mt-3 flex justify-center">
            <AppButton variant="secondary" @click="handleLoadMoreRetry">Reintentar</AppButton>
          </div>
        </div>
      </div>
    </section>

    <AppModal
      :open="isFiltersOpen"
      :actions="[
        { key: 'clear', label: 'Limpiar filtros', tone: 'neutral', icon: ArrowPathIcon },
        { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
      ]"
      title="Filtros"
      variant="default"
      @action="handleFiltersModalAction"
      @close="closeFilters"
    >
      <div class="space-y-5">
        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Frecuencia</AppTitle>
          <AppText>Filtra las reglas según su recurrencia.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="option in frequencyOptions"
            :key="option.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              selectedFrequencies.includes(option.value)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleFrequency(option.value)"
          >
            {{ formatDistributionFrequency(option.value) }}
          </button>
        </div>
      </div>
    </AppModal>

    <AppModal
      :open="isCreateRuleOpen"
      :actions="createRuleActions"
      title="Nueva regla"
      variant="default"
      @close="closeCreateRule"
    >
      <DistributionRuleForm
        form-id="distribution-rule-form"
        :server-error="saveError"
        @state-change="handleCreateFormStateChange"
        @submit="handleCreateRuleSubmit"
      />
    </AppModal>

    <AppModal
      :open="isEditRuleOpen"
      :actions="editRuleActions"
      title="Editar regla"
      variant="default"
      @close="closeEditRule"
    >
      <DistributionRuleForm
        v-if="selectedRule"
        form-id="edit-distribution-rule-form"
        :initial-values="selectedRule"
        :server-error="saveError"
        @state-change="handleEditFormStateChange"
        @submit="handleEditRuleSubmit"
      />
    </AppModal>

    <AppModal
      :open="isDeleteRuleOpen"
      :actions="deleteRuleActions"
      title="Eliminar regla"
      variant="danger"
      @action="$event === 'confirm-delete-rule' && confirmDeleteRule()"
      @close="closeDeleteRule"
    >
      <div class="space-y-3">
        <AppText v-if="selectedRule">
          Vas a eliminar <strong>{{ selectedRule.name }}</strong
          >.
        </AppText>
        <AppText v-if="deleteError" class="text-(--app-color-danger)!">{{ deleteError }}</AppText>
      </div>
    </AppModal>
  </div>
</template>
