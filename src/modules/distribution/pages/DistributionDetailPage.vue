<script setup lang="ts">
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import DistributionRelationForm from '@/modules/distribution/components/DistributionRelationForm.vue';
import DistributionRelationListItem from '@/modules/distribution/components/DistributionRelationListItem.vue';
import { useDistributionRelationsCrud } from '@/modules/distribution/composables/useDistributionRelationsCrud';
import { formatDistributionFrequency } from '@/modules/distribution/schemas/distributionSchemas';
import type { DistributionRelationWritePayload } from '@/modules/distribution/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { AppButton, AppModal, AppSectionBar, AppText, AppTitle } from '@/modules/shared/components';

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
    <AppSectionBar
      :title="rule.name"
      :description="`${formatDistributionFrequency(rule.frequency)} · ${rule.outcomesCount} relaciones registradas`"
    >
      <template #actions>
        <AppButton variant="primary" @click="openCreateRelation">
          <PlusIcon class="h-4 w-4" />
        </AppButton>
      </template>
    </AppSectionBar>

    <section
      v-if="loadError && hasRelations"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <section v-if="isLoading && !hasRelations" class="rounded-2xl border px-4 py-10 text-center">
      <AppText>Cargando relaciones...</AppText>
    </section>

    <section
      v-else-if="loadError && !hasRelations"
      class="space-y-3 rounded-2xl border px-4 py-6 text-center"
    >
      <AppText>{{ loadError }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="secondary" @click="reloadRelations">Reintentar</AppButton>
      </div>
    </section>

    <section v-else class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">{{ relations.length }} relaciones visibles</AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <DistributionRelationListItem
          v-for="relation in relations"
          :key="relation.id"
          :amount="relation.amount"
          :concept="relation.name"
          :type="relation.type"
          @delete="openDeleteRelation(relation.id)"
          @edit="openEditRelation(relation.id)"
        />

        <div
          v-if="relations.length === 0"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">Esta regla todavía no tiene relaciones registradas.</AppText>
        </div>

        <div
          ref="loadMoreSentinel"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">{{ infiniteStatusLabel() }}</AppText>
          <div v-if="loadError && hasRelations" class="mt-3 flex justify-center">
            <AppButton variant="secondary" @click="handleLoadMoreRetry">Reintentar</AppButton>
          </div>
        </div>
      </div>
    </section>

    <AppModal
      :open="isCreateRelationOpen"
      :actions="createRelationActions"
      title="Nueva relación"
      variant="default"
      @close="closeCreateRelation"
    >
      <DistributionRelationForm
        :fixed-income-id="rule.id"
        form-id="distribution-relation-form"
        :server-error="saveError"
        @state-change="handleCreateFormStateChange"
        @submit="handleCreateRelationSubmit"
      />
    </AppModal>

    <AppModal
      :open="isEditRelationOpen"
      :actions="editRelationActions"
      title="Editar relación"
      variant="default"
      @close="closeEditRelation"
    >
      <DistributionRelationForm
        v-if="selectedRelation"
        :fixed-income-id="rule.id"
        form-id="edit-distribution-relation-form"
        :initial-values="selectedRelation"
        :server-error="saveError"
        @state-change="handleEditFormStateChange"
        @submit="handleEditRelationSubmit"
      />
    </AppModal>

    <AppModal
      :open="isDeleteRelationOpen"
      :actions="deleteRelationActions"
      title="Eliminar relación"
      variant="danger"
      @action="$event === 'confirm-delete-relation' && confirmDeleteRelation()"
      @close="closeDeleteRelation"
    >
      <div class="space-y-3">
        <AppText v-if="selectedRelation">
          Vas a eliminar <strong>{{ selectedRelation.name }}</strong
          >.
        </AppText>
        <AppText v-if="deleteError" class="text-(--app-color-danger)!">{{ deleteError }}</AppText>
      </div>
    </AppModal>
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
