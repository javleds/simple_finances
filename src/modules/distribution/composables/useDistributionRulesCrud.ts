import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';

import { createDistributionRepository } from '../repositories/distributionRepository';
import type { DistributionRule, DistributionRuleWritePayload } from '../types';

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

export function useDistributionRulesCrud() {
  const rules = ref<DistributionRule[]>([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasRules = computed(() => rules.value.length > 0);

  async function loadRules(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
      rules.value = await distributionRepository.listRules();
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar las reglas.');
    } finally {
      isLoading.value = false;
    }
  }

  async function createRule(payload: DistributionRuleWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const rule = await distributionRepository.createRule(payload);
      rules.value = [rule, ...rules.value];
      await loadRules();
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible crear la regla.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function updateRule(ruleId: string, payload: DistributionRuleWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedRule = await distributionRepository.updateRule(ruleId, payload);
      rules.value = rules.value.map((rule) => (rule.id === ruleId ? { ...rule, ...updatedRule } : rule));
      await loadRules();
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible actualizar la regla.');
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
      rules.value = rules.value.filter((rule) => rule.id !== ruleId);
      return true;
    } catch (error) {
      deleteError.value = resolveErrorMessage(error, 'No fue posible eliminar la regla.');
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
    rules,
    hasRules,
    isLoading,
    isSaving,
    isDeleting,
    loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadRules,
    createRule,
    updateRule,
    deleteRule,
  };
}
