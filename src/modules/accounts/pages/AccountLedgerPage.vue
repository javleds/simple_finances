<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';
import type { Account, AccountLedgerRow, AccountMemberAmount } from '@/modules/accounts/types';
import { AppButton, AppCard, AppLoadMoreFooter, AppText, AppTitle } from '@/modules/shared/components';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';

const props = defineProps<{ account?: Account }>();

const repository = createAccountsRepository();
const rows = ref<AccountLedgerRow[]>([]);
const isLoading = ref(false);
const isLoadingMore = ref(false);
const loadError = ref<string | null>(null);
const loadMoreError = ref<string | null>(null);
const page = ref(1);
const hasMore = ref(false);

const accountId = computed(() => props.account?.id ?? '');
const canLoadMore = computed(
  () => !isLoading.value && !isLoadingMore.value && hasMore.value && !loadMoreError.value,
);

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: canLoadMore,
  onIntersect: () => {
    void loadMoreLedger();
  },
});

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}

function formatDate(value: string | null): string {
  if (!value) {
    return 'Sin fecha';
  }

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${value}T00:00:00`));
}

function visibleAmounts(amounts: AccountMemberAmount[]): AccountMemberAmount[] {
  return amounts.filter((item) => Math.abs(item.amount) > 0.004);
}

async function loadLedger(nextPage = 1): Promise<void> {
  if (!accountId.value) {
    return;
  }

  const loadingState = nextPage === 1 ? isLoading : isLoadingMore;
  loadingState.value = true;
  if (nextPage === 1) {
    loadError.value = null;
  } else {
    loadMoreError.value = null;
  }

  try {
    const result = await repository.listLedger(accountId.value, {
      page: nextPage,
      perPage: 20,
    });

    rows.value = nextPage === 1 ? result.items : [...rows.value, ...result.items];
    page.value = result.currentPage;
    hasMore.value = result.hasMore;
  } catch (error) {
    const message = error instanceof Error ? error.message : 'No se pudo cargar el libro.';

    if (nextPage === 1) {
      loadError.value = message;
    } else {
      loadMoreError.value = message;
    }
  } finally {
    loadingState.value = false;
  }
}

async function loadMoreLedger(): Promise<void> {
  if (!hasMore.value || isLoadingMore.value) {
    return;
  }

  await loadLedger(page.value + 1);
}

function infiniteStatusLabel(): string {
  if (isLoadingMore.value) {
    return 'Cargando más movimientos del libro...';
  }

  if (loadMoreError.value) {
    return loadMoreError.value;
  }

  if (!hasMore.value) {
    return 'Has llegado al inicio del libro.';
  }

  return 'Sigue desplazándote para revisar más movimientos.';
}

function retryLoadMore(): void {
  void loadMoreLedger();
}

watch(
  accountId,
  () => {
    rows.value = [];
    page.value = 1;
    hasMore.value = false;
    loadMoreError.value = null;
    void loadLedger();
  },
  { immediate: true },
);
</script>

<template>
  <section class="space-y-4">
    <header class="space-y-1">
      <AppTitle as="h2" size="sm">Libro</AppTitle>
      <AppText tone="subtle">
        Balance, custodia y reembolsos después de cada movimiento.
      </AppText>
    </header>

    <AppCard v-if="loadError" class="rounded-2xl border-(--app-color-danger)">
      <div class="space-y-3">
        <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
        <AppButton variant="outline" @click="loadLedger()">Reintentar</AppButton>
      </div>
    </AppCard>

    <div v-else-if="isLoading" class="space-y-3">
      <div
        v-for="index in 4"
        :key="index"
        class="h-32 animate-pulse rounded-2xl bg-(--app-color-surface-muted)"
      />
    </div>

    <AppCard v-else-if="rows.length === 0" class="rounded-2xl">
      <AppText>No hay movimientos en el libro de esta cuenta.</AppText>
    </AppCard>

    <div v-else class="overflow-hidden rounded-2xl border border-(--app-color-border)">
      <article
        v-for="row in rows"
        :key="row.id"
        class="space-y-3 border-b border-(--app-color-border) bg-(--app-color-surface) px-4 py-4 last:border-b-0"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h3 class="truncate text-base font-semibold text-(--app-color-text)">
              {{ row.label }}
            </h3>
            <p class="mt-1 text-xs font-medium text-(--app-color-text-subtle)">
              {{ formatDate(row.occurredAt) }} · {{ row.description }}
            </p>
          </div>
          <div class="shrink-0 text-right">
            <p
              class="text-sm font-bold"
              :class="row.sourceType === 'transaction' && row.amount > 0 ? 'text-(--app-color-text)' : 'text-(--app-color-text)'"
            >
              {{ formatCurrency(row.amount) }}
            </p>
            <p class="text-xs text-(--app-color-text-subtle)">
              Balance {{ formatCurrency(row.balanceAfter) }}
            </p>
          </div>
        </div>

        <div v-if="row.allocations.length" class="flex flex-wrap gap-2">
          <span
            v-for="allocation in row.allocations"
            :key="`${row.id}-${allocation.userId}`"
            class="rounded-full bg-(--app-color-surface-muted) px-3 py-1 text-xs font-semibold text-(--app-color-text-subtle)"
          >
            {{ allocation.userName ?? 'Usuario' }} {{ formatCurrency(allocation.amount) }}
          </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div v-if="visibleAmounts(row.custodyAfterByUser).length" class="space-y-1">
            <p class="text-xs font-semibold uppercase text-(--app-color-text-subtle)">Custodia</p>
            <p
              v-for="custody in visibleAmounts(row.custodyAfterByUser)"
              :key="`${row.id}-custody-${custody.userId}`"
              class="flex justify-between gap-3 text-xs text-(--app-color-text-subtle)"
            >
              <span class="truncate">{{ custody.userName }}</span>
              <span class="shrink-0 font-semibold">{{ formatCurrency(custody.amount) }}</span>
            </p>
          </div>

          <div v-if="visibleAmounts(row.settlementAfterByUser).length" class="space-y-1">
            <p class="text-xs font-semibold uppercase text-(--app-color-text-subtle)">Reembolsos</p>
            <p
              v-for="settlement in visibleAmounts(row.settlementAfterByUser)"
              :key="`${row.id}-settlement-${settlement.userId}`"
              class="flex justify-between gap-3 text-xs text-(--app-color-text-subtle)"
            >
              <span class="truncate">{{ settlement.userName }}</span>
              <span class="shrink-0 font-semibold">{{ formatCurrency(settlement.amount) }}</span>
            </p>
          </div>
        </div>
      </article>
    </div>

    <div v-if="rows.length" ref="loadMoreSentinel">
      <AppLoadMoreFooter
        :label="infiniteStatusLabel()"
        :show-retry="Boolean(loadMoreError)"
        @retry="retryLoadMore"
      />
    </div>
  </section>
</template>
