import { useInfiniteQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, nextTick, ref, shallowRef } from 'vue';

import type { PaginatedCollection } from '@/modules/shared/lib/pagination';

type LoadPageOptions = {
  page: number;
  perPage: number;
};

type UsePaginatedCollectionOptions<TItem, TArgs extends unknown[]> = {
  defaultPerPage?: number;
  loadPage: (options: LoadPageOptions, ...args: TArgs) => Promise<PaginatedCollection<TItem>>;
  resolveErrorMessage: (error: unknown, fallback: string) => string;
  loadErrorMessage: string;
  loadMoreErrorMessage: string;
};

export function usePaginatedCollection<TItem, TArgs extends unknown[]>(
  options: UsePaginatedCollectionOptions<TItem, TArgs>,
) {
  const items = shallowRef<TItem[]>([]);
  const isLoading = ref(false);
  const isLoadingMore = ref(false);
  const loadError = ref<string | null>(null);
  const currentPage = ref(1);
  const lastPage = ref(1);
  const perPage = ref(options.defaultPerPage ?? 20);
  const total = ref<number | null>(null);
  const lastArgs = ref<TArgs | null>(null);
  const queryClient = useQueryClient();
  const queryKey = computed(() => [
    'paginated-collection',
    options.loadErrorMessage,
    perPage.value,
    lastArgs.value ?? [],
  ]);

  const paginatedQuery = useInfiniteQuery({
    queryKey,
    enabled: false,
    initialPageParam: 1,
    queryFn: ({ pageParam }) => {
      if (!lastArgs.value) {
        throw new Error('Paginated collection arguments are required.');
      }

      return options.loadPage(
        {
          page: Number(pageParam),
          perPage: perPage.value,
        },
        ...lastArgs.value,
      );
    },
    getNextPageParam: (lastLoadedPage) =>
      lastLoadedPage.currentPage < lastLoadedPage.lastPage
        ? lastLoadedPage.currentPage + 1
        : undefined,
  });

  const hasItems = computed(() => items.value.length > 0);
  const hasMoreItems = computed(() => currentPage.value < lastPage.value);
  const hasReachedEnd = computed(
    () => hasItems.value && !hasMoreItems.value && !isLoadingMore.value,
  );

  async function load(
    args: TArgs,
    loadOptions?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    const shouldReset = loadOptions?.reset ?? true;
    const nextPerPage = loadOptions?.perPage ?? perPage.value;

    lastArgs.value = args;
    perPage.value = nextPerPage;
    loadError.value = null;

    if (shouldReset) {
      isLoading.value = true;
    } else {
      isLoadingMore.value = true;
    }

    try {
      await nextTick();
      queryClient.removeQueries({ queryKey: queryKey.value, exact: true });
      const response = await paginatedQuery.refetch();

      if (response.error) {
        loadError.value = options.resolveErrorMessage(response.error, options.loadErrorMessage);
        return;
      }

      applyLoadedPages(response.data?.pages ?? []);
    } catch (error) {
      loadError.value = options.resolveErrorMessage(error, options.loadErrorMessage);
    } finally {
      if (shouldReset) {
        isLoading.value = false;
      } else {
        isLoadingMore.value = false;
      }
    }
  }

  async function reload(loadOptions?: { reset?: boolean; perPage?: number }): Promise<void> {
    if (!lastArgs.value) {
      return;
    }

    await load(lastArgs.value, loadOptions);
  }

  async function loadMore(): Promise<void> {
    if (isLoading.value || isLoadingMore.value || !hasMoreItems.value || !lastArgs.value) {
      return;
    }

    isLoadingMore.value = true;
    loadError.value = null;

    try {
      const response = await paginatedQuery.fetchNextPage();

      if (response.error) {
        loadError.value = options.resolveErrorMessage(response.error, options.loadMoreErrorMessage);
        return;
      }

      applyLoadedPages(response.data?.pages ?? []);
    } catch (error) {
      loadError.value = options.resolveErrorMessage(error, options.loadMoreErrorMessage);
    } finally {
      isLoadingMore.value = false;
    }
  }

  function prependItem(item: TItem): void {
    items.value = [item, ...items.value];

    if (typeof total.value === 'number') {
      total.value += 1;
    }
  }

  function prependItems(nextItems: TItem[]): void {
    if (nextItems.length === 0) {
      return;
    }

    items.value = [...nextItems, ...items.value];

    if (typeof total.value === 'number') {
      total.value += nextItems.length;
    }
  }

  function replaceItem(matcher: (item: TItem) => boolean, nextItem: TItem): void {
    items.value = items.value.map((item) => (matcher(item) ? nextItem : item));
  }

  function removeItem(matcher: (item: TItem) => boolean): void {
    const previousLength = items.value.length;
    items.value = items.value.filter((item) => !matcher(item));

    if (typeof total.value === 'number' && items.value.length < previousLength) {
      total.value = Math.max(0, total.value - 1);
    }
  }

  function setItems(nextItems: TItem[]): void {
    items.value = nextItems;
  }

  function applyLoadedPages(pages: Array<PaginatedCollection<TItem>>): void {
    const lastLoadedPage = pages[pages.length - 1];

    items.value = pages.flatMap((page) => page.items);
    currentPage.value = lastLoadedPage?.currentPage ?? 1;
    lastPage.value = lastLoadedPage?.lastPage ?? 1;
    total.value = lastLoadedPage?.total ?? null;
  }

  return {
    items,
    isLoading,
    isLoadingMore,
    loadError,
    currentPage,
    lastPage,
    perPage,
    total,
    hasItems,
    hasMoreItems,
    hasReachedEnd,
    load,
    reload,
    loadMore,
    prependItem,
    prependItems,
    replaceItem,
    removeItem,
    setItems,
  };
}
