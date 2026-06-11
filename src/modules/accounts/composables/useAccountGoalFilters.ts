import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { AccountGoalListFilters } from '@/modules/accounts/schemas/accountGoalSchemas';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';

export type AccountGoalStatusFilter = NonNullable<AccountGoalListFilters['status']>[number];

export function useAccountGoalFilters() {
  const route = useRoute();
  const router = useRouter();
  const searchTerm = ref('');
  const selectedStatuses = ref<AccountGoalStatusFilter[]>([]);

  const activeFilters = computed<AccountGoalListFilters>(() => ({
    search: searchTerm.value.trim() || undefined,
    status: selectedStatuses.value.length > 0 ? [...selectedStatuses.value] : undefined,
  }));

  watch(
    () => route.query,
    (nextQuery) => {
      searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
      selectedStatuses.value = parseQueryValues(nextQuery.status, isGoalStatus);
    },
    { immediate: true },
  );

  watch(
    [searchTerm, selectedStatuses],
    () => {
      const nextQuery = {
        ...route.query,
        search: searchTerm.value.trim() || undefined,
        status: selectedStatuses.value.length > 0 ? selectedStatuses.value.join(',') : undefined,
      };

      if (areQueriesEqual(route.query, nextQuery)) {
        return;
      }

      void router.replace({ query: nextQuery });
    },
    { deep: true },
  );

  function clearFilters(): void {
    selectedStatuses.value = [];
  }

  function toggleStatus(status: AccountGoalStatusFilter): void {
    if (selectedStatuses.value.includes(status)) {
      selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
      return;
    }

    selectedStatuses.value = [...selectedStatuses.value, status];
  }

  return {
    activeFilters,
    clearFilters,
    searchTerm,
    selectedStatuses,
    toggleStatus,
  };
}

function isGoalStatus(value: string): value is AccountGoalStatusFilter {
  return value === 'on-track' || value === 'at-risk' || value === 'completed';
}
