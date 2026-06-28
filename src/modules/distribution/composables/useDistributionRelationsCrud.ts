import { computed, ref } from 'vue';

import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { createDistributionRepository } from '../repositories/distributionRepository';
import type {
  DistributionRelation,
  DistributionRelationWritePayload,
  DistributionRule,
} from '../types';

const distributionRepository = createDistributionRepository();

export function useDistributionRelationsCrud() {
  const rule = ref<DistributionRule | null>(null);
  const relationsState = usePaginatedCollection<DistributionRelation, [string]>({
    defaultPerPage: 20,
    loadPage: (options, fixedIncomeId) =>
      distributionRepository.listRelations(fixedIncomeId, options),
    resolveErrorMessage: resolveApiErrorMessage,
    loadErrorMessage: 'No fue posible cargar la regla.',
    loadMoreErrorMessage: 'No fue posible cargar más relaciones.',
  });
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasRelations = computed(() => relationsState.hasItems.value);
  const hasMoreRelations = computed(() => relationsState.hasMoreItems.value);
  const hasReachedEnd = computed(() => relationsState.hasReachedEnd.value);

  async function loadRule(
    ruleId: string,
    options?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    relationsState.loadError.value = null;

    try {
      relationsState.isLoading.value = true;

      const [loadedRule] = await Promise.all([distributionRepository.getRule(ruleId)]);
      rule.value = loadedRule;
    } catch (error) {
      relationsState.loadError.value = resolveApiErrorMessage(
        error,
        'No fue posible cargar la regla.',
      );
    } finally {
      relationsState.isLoading.value = false;
    }

    await relationsState.load([ruleId], options);
  }

  async function loadMoreRelations(): Promise<void> {
    await relationsState.loadMore();
  }

  async function createRelation(payload: DistributionRelationWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const relation = await distributionRepository.createRelation(payload);
      relationsState.prependItem(relation);
      await loadRule(payload.fixedIncomeId);
      return true;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible crear la relación.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function updateRelation(
    relationId: string,
    payload: DistributionRelationWritePayload,
  ): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedRelation = await distributionRepository.updateRelation(relationId, payload);
      relationsState.replaceItem((relation) => relation.id === relationId, updatedRelation);
      await loadRule(payload.fixedIncomeId);
      return true;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible actualizar la relación.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function deleteRelation(relationId: string, fixedIncomeId: string): Promise<boolean> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      await distributionRepository.removeRelation(relationId);
      relationsState.removeItem((relation) => relation.id === relationId);
      await loadRule(fixedIncomeId);
      return true;
    } catch (error) {
      deleteError.value = resolveApiErrorMessage(error, 'No fue posible eliminar la relación.');
      return false;
    } finally {
      isDeleting.value = false;
    }
  }

  function clearSaveError(): void {
    saveError.value = null;
  }

  function clearDeleteError(): void {
    deleteError.value = null;
  }

  return {
    rule,
    relations: relationsState.items,
    hasRelations,
    hasMoreRelations,
    hasReachedEnd,
    isLoading: relationsState.isLoading,
    isLoadingMore: relationsState.isLoadingMore,
    isSaving,
    isDeleting,
    loadError: relationsState.loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadRule,
    loadMoreRelations,
    createRelation,
    updateRelation,
    deleteRelation,
    perPage: relationsState.perPage,
  };
}
