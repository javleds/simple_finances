import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type {
  SubscriptionFrequencyType,
  SubscriptionListFilters,
  SubscriptionStatusFilter,
} from '@/modules/subscriptions/types';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';

const defaultSubscriptionStatuses: SubscriptionStatusFilter[] = ['active'];

export function useSubscriptionFilters() {
  const route = useRoute();
  const router = useRouter();
  const searchTerm = ref(typeof route.query.search === 'string' ? route.query.search : '');
  const selectedStatuses = ref<SubscriptionStatusFilter[]>(
    parseQueryValuesOrDefault(
      route.query.status,
      isSubscriptionStatusFilter,
      defaultSubscriptionStatuses,
    ),
  );
  const selectedUnits = ref<SubscriptionFrequencyType[]>(
    parseQueryValues(route.query.frequencyType, isSubscriptionFrequencyType),
  );

  const activeFilters = computed<SubscriptionListFilters>(() => ({
    search: searchTerm.value.trim() || undefined,
    status: selectedStatuses.value.length > 0 ? [...selectedStatuses.value] : undefined,
    frequencyType: selectedUnits.value.length > 0 ? [...selectedUnits.value] : undefined,
  }));

  watch(
    () => route.query,
    (nextQuery) => {
      searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
      setArrayValueIfChanged(
        selectedStatuses,
        parseQueryValues(nextQuery.status, isSubscriptionStatusFilter),
      );
      setArrayValueIfChanged(
        selectedUnits,
        parseQueryValues(nextQuery.frequencyType, isSubscriptionFrequencyType),
      );
    },
  );

  watch(
    [searchTerm, selectedStatuses, selectedUnits],
    () => {
      const nextQuery = {
        ...route.query,
        search: searchTerm.value.trim() || undefined,
        status: selectedStatuses.value.length > 0 ? selectedStatuses.value.join(',') : undefined,
        frequencyType: selectedUnits.value.length > 0 ? selectedUnits.value.join(',') : undefined,
      };

      if (areQueriesEqual(route.query, nextQuery)) {
        return;
      }

      void router.replace({ query: nextQuery });
    },
    { deep: true, immediate: true },
  );

  function clearFilters(): void {
    selectedStatuses.value = [];
    selectedUnits.value = [];
  }

  function toggleStatus(status: SubscriptionStatusFilter): void {
    if (selectedStatuses.value.includes(status)) {
      selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
      return;
    }

    selectedStatuses.value = [status];
  }

  function toggleUnit(unit: SubscriptionFrequencyType): void {
    if (selectedUnits.value.includes(unit)) {
      selectedUnits.value = selectedUnits.value.filter((item) => item !== unit);
      return;
    }

    selectedUnits.value = [...selectedUnits.value, unit];
  }

  return {
    activeFilters,
    clearFilters,
    searchTerm,
    selectedStatuses,
    selectedUnits,
    toggleStatus,
    toggleUnit,
  };
}

function parseQueryValuesOrDefault<TValue extends string>(
  value: unknown,
  isAllowedValue: (value: string) => value is TValue,
  defaultValues: TValue[],
): TValue[] {
  if (typeof value !== 'string') {
    return [...defaultValues];
  }

  return parseQueryValues(value, isAllowedValue);
}

function setArrayValueIfChanged<TValue>(
  target: { value: TValue[] },
  nextValue: TValue[],
): void {
  if (areArraysEqual(target.value, nextValue)) {
    return;
  }

  target.value = nextValue;
}

function areArraysEqual<TValue>(currentValue: TValue[], nextValue: TValue[]): boolean {
  if (currentValue.length !== nextValue.length) {
    return false;
  }

  return currentValue.every((item, index) => item === nextValue[index]);
}

function isSubscriptionStatusFilter(value: string): value is SubscriptionStatusFilter {
  return value === 'active' || value === 'cancelled';
}

function isSubscriptionFrequencyType(value: string): value is SubscriptionFrequencyType {
  return value === 'days' || value === 'months' || value === 'years';
}
