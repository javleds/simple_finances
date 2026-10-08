import { computed, ref } from 'vue';

import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { createDistributionRepository } from '../repositories/distributionRepository';
import type {
  DistributionRule,
  DistributionRuleListFilters,
  DistributionRuleWritePayload,
} from '../types';

const distributionRepository = createDistributionRepository();

export function useDistributionRulesCrud() {
  const rulesState = usePaginatedCollection<
    DistributionRule,
    [DistributionRuleListFilters | undefined]
  >({
    defaultPerPage: 20,
    loadPage: (options, filters) => distributionRepository.listRules({ ...options, filters }),
    resolveErrorMessage: resolveApiErrorMessage,
    loadErrorMessage: 'No fue posible cargar las reglas.',
    loadMoreErrorMessage: 'No fue posible cargar más reglas.',
  });
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasRules = computed(() => rulesState.hasItems.value);
  const hasMoreRules = computed(() => rulesState.hasMoreItems.value);
  const hasReachedEnd = computed(() => rulesState.hasReachedEnd.value);

  async function loadRules(
    filters?: DistributionRuleListFilters,
    options?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    await rulesState.load([filters], options);
  }

  async function loadMoreRules(): Promise<void> {
    await rulesState.loadMore();
  }

  async function createRule(payload: DistributionRuleWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const rule = await distributionRepository.createRule(payload);
      rulesState.prependItem(rule);
      await rulesState.reload();
      return true;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible crear la regla.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function updateRule(
    ruleId: string,
    payload: DistributionRuleWritePayload,
  ): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedRule = await distributionRepository.updateRule(ruleId, payload);
      rulesState.replaceItem((rule) => rule.id === ruleId, { ...updatedRule });
      await rulesState.reload();
      return true;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible actualizar la regla.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function deleteRule(ruleId: string): Promise<boolean> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      await distributionRepository.removeRule(ruleId);
      rulesState.removeItem((rule) => rule.id === ruleId);
      return true;
    } catch (error) {
      deleteError.value = resolveApiErrorMessage(error, 'No fue posible eliminar la regla.');
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
    rules: rulesState.items,
    hasRules,
    hasMoreRules,
    hasReachedEnd,
    isLoading: rulesState.isLoading,
    isLoadingMore: rulesState.isLoadingMore,
    isSaving,
    isDeleting,
    loadError: rulesState.loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadRules,
    loadMoreRules,
    createRule,
    updateRule,
    deleteRule,
    perPage: rulesState.perPage,
  };
}
