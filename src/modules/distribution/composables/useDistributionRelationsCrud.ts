import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';

import { createDistributionRepository } from '../repositories/distributionRepository';
import type { DistributionRelation, DistributionRelationWritePayload, DistributionRule } from '../types';

const distributionRepository = createDistributionRepository();

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export function useDistributionRelationsCrud() {
  const rule = ref<DistributionRule | null>(null);
  const relations = ref<DistributionRelation[]>([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasRelations = computed(() => relations.value.length > 0);

  async function loadRule(ruleId: string): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
      const [loadedRule, loadedRelations] = await Promise.all([
        distributionRepository.getRule(ruleId),
        distributionRepository.listRelations(ruleId),
      ]);
      rule.value = loadedRule;
      relations.value = loadedRelations;
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar la regla.');
    } finally {
      isLoading.value = false;
    }
  }

  async function createRelation(payload: DistributionRelationWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const relation = await distributionRepository.createRelation(payload);
      relations.value = [relation, ...relations.value];
      await loadRule(payload.fixedIncomeId);
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible crear la relación.');
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
      relations.value = relations.value.map((relation) =>
        relation.id === relationId ? updatedRelation : relation,
      );
      await loadRule(payload.fixedIncomeId);
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible actualizar la relación.');
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
      relations.value = relations.value.filter((relation) => relation.id !== relationId);
      await loadRule(fixedIncomeId);
      return true;
    } catch (error) {
      deleteError.value = resolveErrorMessage(error, 'No fue posible eliminar la relación.');
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
    relations,
    hasRelations,
    isLoading,
    isSaving,
    isDeleting,
    loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadRule,
    createRelation,
    updateRelation,
    deleteRelation,
  };
}
