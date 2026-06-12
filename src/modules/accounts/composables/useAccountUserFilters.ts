import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { AccountUsersListFilters } from '@/modules/accounts/types';
import { areQueriesEqual } from '@/modules/shared/lib/queryParams';

export function useAccountUserFilters(options: { onChange: () => void }) {
  const route = useRoute();
  const router = useRouter();
  const searchTerm = ref('');

  const activeFilters = computed<AccountUsersListFilters>(() => ({
    search: searchTerm.value.trim() || undefined,
  }));

  watch(
    () => route.query.search,
    (nextSearch) => {
      searchTerm.value = typeof nextSearch === 'string' ? nextSearch : '';
    },
    { immediate: true },
  );

  watch(searchTerm, () => {
    const nextQuery = {
      ...route.query,
      search: searchTerm.value.trim() || undefined,
    };

    if (areQueriesEqual(route.query, nextQuery)) {
      return;
    }

    void router.replace({ query: nextQuery });
    options.onChange();
  });

  return {
    activeFilters,
    searchTerm,
  };
}
