<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import DistributionRelationDeleteModal from '@/modules/distribution/components/DistributionRelationDeleteModal.vue';
import DistributionRelationFormModal from '@/modules/distribution/components/DistributionRelationFormModal.vue';
import DistributionRelationsHeader from '@/modules/distribution/components/DistributionRelationsHeader.vue';
import DistributionRelationsList from '@/modules/distribution/components/DistributionRelationsList.vue';
import { useDistributionRelationsCrud } from '@/modules/distribution/composables/useDistributionRelationsCrud';
import type { DistributionRelationWritePayload } from '@/modules/distribution/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import {
  AppButton,
  AppCard,
  AppListState,
  AppLoadMoreFooter,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const route = useRoute();
const defaultRelationsPerPage = 20;

const ruleId = computed(() => (typeof route.params.ruleId === 'string' ? route.params.ruleId : ''));

const isCreateRelationOpen = ref(false);
const isEditRelationOpen = ref(false);
const isDeleteRelationOpen = ref(false);
const selectedRelationId = ref<string | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });

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

const relationsPerPage = computed(() => {
  const rawValue = typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultRelationsPerPage;
  }

  return rawValue;
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreRelations.value),
  onIntersect: () => {
    void loadMoreRelations();
  },
});

const selectedRelation = computed(() => {
  if (!selectedRelationId.value) {
    return null;
  }

  return relations.value.find((relation) => relation.id === selectedRelationId.value) ?? null;
});

const createRelationActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-relation',
    label: isSaving.value ? 'Guardando...' : 'Crear relación',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'distribution-relation-form',
    disabled: !createFormState.value.canSubmit || isSaving.value,
  },
]);

const editRelationActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-edit-relation',
    label: isSaving.value ? 'Guardando...' : 'Guardar cambios',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'edit-distribution-relation-form',
    disabled: !editFormState.value.canSubmit || isSaving.value,
  },
]);

const deleteRelationActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-delete-relation',
    label: isDeleting.value ? 'Eliminando...' : 'Eliminar relación',
    tone: 'primary' as const,
    disabled: !selectedRelation.value || isDeleting.value,
  },
]);

watch(
  [ruleId, relationsPerPage],
  ([nextRuleId, nextPerPage]) => {
    if (!nextRuleId) {
      return;
    }

    void loadRule(nextRuleId, {
      reset: true,
      perPage: nextPerPage,
    });
  },
  { immediate: true },
);

function openCreateRelation(): void {
  clearSaveError();
  createFormState.value = { canSubmit: false, isSubmitting: false };
  isCreateRelationOpen.value = true;
}

function closeCreateRelation(): void {
  isCreateRelationOpen.value = false;
  clearSaveError();
}

function openEditRelation(relationId: string): void {
  clearSaveError();
  selectedRelationId.value = relationId;
  editFormState.value = { canSubmit: false, isSubmitting: false };
  isEditRelationOpen.value = true;
}

function closeEditRelation(): void {
  isEditRelationOpen.value = false;
  selectedRelationId.value = null;
  clearSaveError();
}

function openDeleteRelation(relationId: string): void {
  clearDeleteError();
  selectedRelationId.value = relationId;
  isDeleteRelationOpen.value = true;
}

function closeDeleteRelation(): void {
  isDeleteRelationOpen.value = false;
  selectedRelationId.value = null;
  clearDeleteError();
}

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

function handleCreateFormStateChange(state: FormState): void {
  createFormState.value = state;
}

function handleEditFormStateChange(state: FormState): void {
  editFormState.value = state;
}

function reloadRelations(): void {
  if (!ruleId.value) {
    return;
  }

  void loadRule(ruleId.value, {
    reset: true,
    perPage: relationsPerPage.value,
  });
}

function handleLoadMoreRetry(): void {
  void loadMoreRelations();
}

function infiniteStatusLabel(): string {
  if (isLoadingMore.value) {
    return 'Cargando más relaciones...';
  }

  if (hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para revisar más relaciones conforme crezca la regla.';
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
