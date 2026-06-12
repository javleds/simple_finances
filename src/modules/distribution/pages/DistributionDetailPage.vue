<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import DistributionRelationDeleteModal from '@/modules/distribution/components/DistributionRelationDeleteModal.vue';
import DistributionRelationFormModal from '@/modules/distribution/components/DistributionRelationFormModal.vue';
import DistributionRelationsHeader from '@/modules/distribution/components/DistributionRelationsHeader.vue';
import DistributionRelationsList from '@/modules/distribution/components/DistributionRelationsList.vue';
import { useDistributionRelationListLoader } from '@/modules/distribution/composables/useDistributionRelationListLoader';
import { useDistributionRelationModalActions } from '@/modules/distribution/composables/useDistributionRelationModalActions';
import { useDistributionRelationModals } from '@/modules/distribution/composables/useDistributionRelationModals';
import { useDistributionRelationsCrud } from '@/modules/distribution/composables/useDistributionRelationsCrud';
import type { DistributionRelationWritePayload } from '@/modules/distribution/types';
import {
  AppCard,
  AppListState,
  AppLoadMoreFooter,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const route = useRoute();

const ruleId = computed(() => (typeof route.params.ruleId === 'string' ? route.params.ruleId : ''));

const {
  rule,
  relations,
  hasRelations,
  hasMoreRelations,
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
  loadRule,
  loadMoreRelations,
  createRelation,
  updateRelation,
  deleteRelation,
} = useDistributionRelationsCrud();

const {
  closeCreateRelation,
  closeDeleteRelation,
  closeEditRelation,
  createFormState,
  editFormState,
  handleCreateFormStateChange,
  handleEditFormStateChange,
  isCreateRelationOpen,
  isDeleteRelationOpen,
  isEditRelationOpen,
  openCreateRelation,
  openDeleteRelation,
  openEditRelation,
  selectedRelation,
} = useDistributionRelationModals({
  clearDeleteError,
  clearSaveError,
  relations,
});

const {
  createRelationActions,
  deleteRelationActions,
  editRelationActions,
} = useDistributionRelationModalActions({
  createFormState,
  editFormState,
  isDeleting,
  isSaving,
  selectedRelation,
});

const {
  handleLoadMoreRetry,
  infiniteStatusLabel,
  loadMoreSentinel,
  reloadRelations,
} = useDistributionRelationListLoader({
  hasMoreRelations,
  hasReachedEnd,
  isLoading,
  isLoadingMore,
  loadMoreRelations,
  loadRule,
  ruleId,
});

async function handleCreateRelationSubmit(
  payload: DistributionRelationWritePayload,
): Promise<void> {
  const wasCreated = await createRelation(payload);

  if (wasCreated) {
    closeCreateRelation();
  }
}

async function handleEditRelationSubmit(payload: DistributionRelationWritePayload): Promise<void> {
  if (!selectedRelation.value) {
    return;
  }

  const wasUpdated = await updateRelation(selectedRelation.value.id, payload);

  if (wasUpdated) {
    closeEditRelation();
  }
}

async function confirmDeleteRelation(): Promise<void> {
  if (!selectedRelation.value || !rule.value) {
    return;
  }

  const wasDeleted = await deleteRelation(selectedRelation.value.id, rule.value.id);

  if (wasDeleted) {
    closeDeleteRelation();
  }
}

</script>

<template>
  <section v-if="rule" class="space-y-4">
    <DistributionRelationsHeader :rule="rule" @create="openCreateRelation" />

    <section
      v-if="loadError && hasRelations"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <AppListState
      :error="loadError"
      :has-items="hasRelations"
      :is-loading="isLoading"
      loading-label="Cargando relaciones..."
      @retry="reloadRelations"
    >
      <DistributionRelationsList
        :relations="relations"
        @delete="openDeleteRelation"
        @edit="openEditRelation"
      >
        <template #footer>
          <div ref="loadMoreSentinel">
            <AppLoadMoreFooter
              :label="infiniteStatusLabel()"
              :show-retry="Boolean(loadError && hasRelations)"
              @retry="handleLoadMoreRetry"
            />
          </div>
        </template>
      </DistributionRelationsList>
    </AppListState>

    <DistributionRelationFormModal
      :open="isCreateRelationOpen"
      :actions="createRelationActions"
      :fixed-income-id="rule.id"
      form-id="distribution-relation-form"
      :server-error="saveError"
      title="Nueva relación"
      @close="closeCreateRelation"
      @state-change="handleCreateFormStateChange"
      @submit="handleCreateRelationSubmit"
    />

    <DistributionRelationFormModal
      :open="isEditRelationOpen"
      :actions="editRelationActions"
      :fixed-income-id="rule.id"
      form-id="edit-distribution-relation-form"
      :initial-values="selectedRelation"
      requires-initial-values
      :server-error="saveError"
      title="Editar relación"
      @close="closeEditRelation"
      @state-change="handleEditFormStateChange"
      @submit="handleEditRelationSubmit"
    />

    <DistributionRelationDeleteModal
      :open="isDeleteRelationOpen"
      :actions="deleteRelationActions"
      :delete-error="deleteError"
      :relation="selectedRelation"
      @close="closeDeleteRelation"
      @confirm="confirmDeleteRelation"
    />
  </section>

  <AppCard v-else class="rounded-3xl">
    <div v-if="loadError" class="space-y-2">
      <AppTitle as="h2" size="sm">No fue posible cargar la regla</AppTitle>
      <AppText>{{ loadError }}</AppText>
    </div>

    <div v-else class="space-y-2">
      <AppTitle as="h2" size="sm">Regla no encontrada</AppTitle>
      <AppText>No encontramos un ingreso fijo con ese identificador.</AppText>
    </div>
  </AppCard>
</template>
