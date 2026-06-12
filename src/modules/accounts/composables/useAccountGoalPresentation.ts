import { computed, type Ref } from 'vue';

import type { AccountGoal } from '@/modules/accounts/schemas/accountGoalSchemas';
import type { AccountGoalStatusFilter } from '@/modules/accounts/composables/useAccountGoalFilters';

type UseAccountGoalPresentationOptions = {
  goals: Ref<AccountGoal[]>;
  hasReachedEnd: Ref<boolean>;
  isLoadingMore: Ref<boolean>;
  selectedStatuses: Ref<AccountGoalStatusFilter[]>;
};

export function useAccountGoalPresentation(options: UseAccountGoalPresentationOptions) {
  const filteredGoalItems = computed(() => {
    return options.goals.value.filter((goal) => {
      const status = goal.status === 'completed' ? 'completed' : resolveGoalStatus(goal.progress);

      if (options.selectedStatuses.value.length > 0 && !options.selectedStatuses.value.includes(status)) {
        return false;
      }

      return true;
    });
  });

  function infiniteStatusLabel(): string {
    if (options.isLoadingMore.value) {
      return 'Cargando más metas...';
    }

    if (options.hasReachedEnd.value) {
      return 'Has llegado al final.';
    }

    return 'Sigue desplazándote para revisar más metas conforme la cuenta acumule objetivos.';
  }

  return {
    filteredGoalItems,
    infiniteStatusLabel,
  };
}

function resolveGoalStatus(progress: number): AccountGoalStatusFilter {
  if (progress >= 100) {
    return 'completed';
  }

  if (progress < 50) {
    return 'at-risk';
  }

  return 'on-track';
}
