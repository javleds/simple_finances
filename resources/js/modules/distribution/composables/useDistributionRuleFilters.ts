import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type {
  DistributionFrequency,
  DistributionRuleListFilters,
} from '@/modules/distribution/types';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';

export function useDistributionRuleFilters() {
  const route = useRoute();
  const router = useRouter();
  const searchTerm = ref('');
  const selectedFrequencies = ref<DistributionFrequency[]>([]);

  const activeFilters = computed<DistributionRuleListFilters>(() => ({
    search: searchTerm.value.trim() || undefined,
    frequency: selectedFrequencies.value.length > 0 ? [...selectedFrequencies.value] : undefined,
  }));

  watch(
    () => route.query,
    (nextQuery) => {
      searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
      selectedFrequencies.value = parseQueryValues(nextQuery.frequency, isDistributionFrequency);
    },
    { immediate: true },
  );

  watch(
    [searchTerm, selectedFrequencies],
    () => {
      const nextQuery = {
        ...route.query,
        search: searchTerm.value.trim() || undefined,
        frequency:
          selectedFrequencies.value.length > 0 ? selectedFrequencies.value.join(',') : undefined,
      };

      if (areQueriesEqual(route.query, nextQuery)) {
        return;
      }

      void router.replace({ query: nextQuery });
    },
    { deep: true },
  );

  function clearFilters(): void {
    selectedFrequencies.value = [];
  }

  function toggleFrequency(frequency: DistributionFrequency): void {
    if (selectedFrequencies.value.includes(frequency)) {
      selectedFrequencies.value = selectedFrequencies.value.filter((item) => item !== frequency);
      return;
    }

    selectedFrequencies.value = [...selectedFrequencies.value, frequency];
  }

  return {
    activeFilters,
    clearFilters,
    searchTerm,
    selectedFrequencies,
    toggleFrequency,
  };
}

function isDistributionFrequency(value: string): value is DistributionFrequency {
  return value === 'monthly' || value === 'semi_monthly';
}
