import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type {
  AccountKindFilter,
  AccountListFilters,
  AccountSurfaceFilter,
  AccountStatus,
} from '@/modules/accounts/types';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';

const defaultAccountStatuses: AccountStatus[] = ['Activo'];
const defaultAccountSurfaces: AccountSurfaceFilter[] = ['physical'];
const availableStatuses: AccountStatus[] = ['Activo', 'Inactivo'];
const availableKinds: AccountKindFilter[] = ['credit', 'debit'];
const availableSurfaces: AccountSurfaceFilter[] = ['virtual', 'physical'];

export function useAccountFilters() {
  const route = useRoute();
  const router = useRouter();
  const searchTerm = ref(typeof route.query.search === 'string' ? route.query.search : '');
  const selectedStatuses = ref<AccountStatus[]>(
    parseQueryValuesOrDefault(route.query.status, isAccountStatus, defaultAccountStatuses),
  );
  const selectedKinds = ref<AccountKindFilter[]>(
    parseQueryValues(route.query.kind, isAccountKindFilter),
  );
  const selectedSurfaces = ref<AccountSurfaceFilter[]>(
    parseQueryValuesOrDefault(route.query.surface, isAccountSurfaceFilter, defaultAccountSurfaces),
  );

  const activeFilters = computed<AccountListFilters>(() => ({
    search: searchTerm.value.trim() || undefined,
    status: selectedStatuses.value.length > 0 ? [...selectedStatuses.value] : undefined,
    kind: selectedKinds.value.length > 0 ? [...selectedKinds.value] : undefined,
    surface: selectedSurfaces.value.length > 0 ? [...selectedSurfaces.value] : undefined,
  }));

  watch(
    () => route.query,
    (nextQuery) => {
      searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
      setArrayValueIfChanged(selectedStatuses, parseQueryValues(nextQuery.status, isAccountStatus));
      setArrayValueIfChanged(selectedKinds, parseQueryValues(nextQuery.kind, isAccountKindFilter));
      setArrayValueIfChanged(
        selectedSurfaces,
        parseQueryValues(nextQuery.surface, isAccountSurfaceFilter),
      );
    },
  );

  watch(
    [searchTerm, selectedStatuses, selectedKinds, selectedSurfaces],
    () => {
      const nextQuery = {
        ...route.query,
        search: searchTerm.value.trim() || undefined,
        status: selectedStatuses.value.length > 0 ? selectedStatuses.value.join(',') : undefined,
        kind: selectedKinds.value.length > 0 ? selectedKinds.value.join(',') : undefined,
        surface: selectedSurfaces.value.length > 0 ? selectedSurfaces.value.join(',') : undefined,
      };

      if (areQueriesEqual(route.query, nextQuery)) {
        return;
      }

      void router.replace({ query: nextQuery });
    },
    { deep: true, immediate: true },
  );

  function clearFilters(): void {
    searchTerm.value = '';
    selectedStatuses.value = [];
    selectedKinds.value = [];
    selectedSurfaces.value = [];
  }

  function toggleStatus(status: AccountStatus): void {
    if (selectedStatuses.value.includes(status)) {
      selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
      return;
    }

    selectedStatuses.value = [...selectedStatuses.value, status];
  }

  function toggleKind(kind: AccountKindFilter): void {
    if (selectedKinds.value.includes(kind)) {
      selectedKinds.value = selectedKinds.value.filter((item) => item !== kind);
      return;
    }

    selectedKinds.value = [...selectedKinds.value, kind];
  }

  function toggleSurface(surface: AccountSurfaceFilter): void {
    if (selectedSurfaces.value.includes(surface)) {
      selectedSurfaces.value = selectedSurfaces.value.filter((item) => item !== surface);
      return;
    }

    selectedSurfaces.value = [...selectedSurfaces.value, surface];
  }

  return {
    activeFilters,
    clearFilters,
    searchTerm,
    selectedKinds,
    selectedStatuses,
    selectedSurfaces,
    toggleKind,
    toggleStatus,
    toggleSurface,
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

function setArrayValueIfChanged<TValue>(target: { value: TValue[] }, nextValue: TValue[]): void {
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

function isAccountStatus(value: string): value is AccountStatus {
  return availableStatuses.includes(value as AccountStatus);
}

function isAccountKindFilter(value: string): value is AccountKindFilter {
  return availableKinds.includes(value as AccountKindFilter);
}

function isAccountSurfaceFilter(value: string): value is AccountSurfaceFilter {
  return availableSurfaces.includes(value as AccountSurfaceFilter);
}
