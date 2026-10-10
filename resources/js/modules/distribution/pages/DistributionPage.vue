<script setup lang="ts">
import { computed } from 'vue';
import Message from 'primevue/message';
import DistributionRuleDeleteModal from '@/modules/distribution/components/DistributionRuleDeleteModal.vue';
import DistributionRuleFiltersModal from '@/modules/distribution/components/DistributionRuleFiltersModal.vue';
import DistributionRuleFormModal from '@/modules/distribution/components/DistributionRuleFormModal.vue';
import DistributionRulesList from '@/modules/distribution/components/DistributionRulesList.vue';
import DistributionRulesToolbar from '@/modules/distribution/components/DistributionRulesToolbar.vue';
import { useDistributionRuleFilters } from '@/modules/distribution/composables/useDistributionRuleFilters';
import { useDistributionRuleListLoader } from '@/modules/distribution/composables/useDistributionRuleListLoader';
import { useDistributionRuleModalActions } from '@/modules/distribution/composables/useDistributionRuleModalActions';
import { useDistributionRuleModals } from '@/modules/distribution/composables/useDistributionRuleModals';
import { useDistributionRulesCrud } from '@/modules/distribution/composables/useDistributionRulesCrud';
import type { DistributionRuleWritePayload } from '@/modules/distribution/types';
import { AppActiveFilters, AppListState, AppLoadMoreFooter } from '@/modules/shared/components';

const frequencyOptions = [
    { value: 'monthly', label: 'Mensual' },
    { value: 'semi_monthly', label: 'Quincenal' },
] as const;
const { activeFilters, applyFilters, searchTerm, selectedFrequencies, toggleFrequency } =
    useDistributionRuleFilters();

const activeFilterChips = computed(() => [
    ...selectedFrequencies.value.map((value) => ({
        key: `selectedFrequencies-${value}`,
        label: frequencyOptions.find((option) => option.value === value)?.label ?? value,
        remove: () => toggleFrequency(value),
    })),
]);

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

const { createRuleActions, deleteRuleActions, editRuleActions } = useDistributionRuleModalActions({
    createFormState,
    editFormState,
    isDeleting,
    isSaving,
    selectedRule,
});

const { handleLoadMoreRetry, infiniteStatusLabel, loadMoreSentinel, reloadRules } =
    useDistributionRuleListLoader({
        activeFilters,
        hasMoreRules,
        hasReachedEnd,
        isLoading,
        isLoadingMore,
        loadMoreRules,
        loadRules,
    });

async function handleCreateRuleSubmit(payload: DistributionRuleWritePayload): Promise<void> {
    if (isSaving.value) return;
    const wasCreated = await createRule(payload);

    if (wasCreated) {
        closeCreateRule();
    }
}

async function handleEditRuleSubmit(payload: DistributionRuleWritePayload): Promise<void> {
    if (isSaving.value) return;
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
</script>

<template>
    <div class="space-y-5">
        <DistributionRulesToolbar
            v-model:search-term="searchTerm"
            :active-filter-count="activeFilterChips.length"
            :filters-open="isFiltersOpen"
            @create="openCreateRule"
            @open-filters="openFilters"
        />
        <AppActiveFilters :filters="activeFilterChips" />

        <Message v-if="loadError && hasRules" severity="error">{{ loadError }}</Message>

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
            @apply="applyFilters"
            @close="closeFilters"
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
