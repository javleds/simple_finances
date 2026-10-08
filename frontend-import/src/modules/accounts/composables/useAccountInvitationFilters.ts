import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type {
  AccountInviteListFilters,
  AccountInviteStatus,
} from '@/modules/accounts/schemas/accountInviteSchemas';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';

export function useAccountInvitationFilters() {
  const route = useRoute();
  const router = useRouter();
  const searchTerm = ref('');
  const selectedStatuses = ref<AccountInviteStatus[]>([]);

  const activeFilters = computed<AccountInviteListFilters>(() => ({
    search: searchTerm.value.trim() || undefined,
    status: selectedStatuses.value.length > 0 ? [...selectedStatuses.value] : undefined,
  }));

  watch(
    () => route.query,
    (nextQuery) => {
      searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
      selectedStatuses.value = parseQueryValues(nextQuery.status, isInvitationStatus);
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

  function toggleStatus(status: AccountInviteStatus): void {
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

function isInvitationStatus(value: string): value is AccountInviteStatus {
  return value === 'pending' || value === 'accepted' || value === 'declined';
}
