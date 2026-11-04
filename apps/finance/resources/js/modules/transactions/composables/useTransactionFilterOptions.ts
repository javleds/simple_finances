import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { createTransactionsRepository } from '../repositories/transactionsRepository';
import { uncategorizedFilterValue } from '../lib/transactionAdditionalFilters';

const repository = createTransactionsRepository();
export function useTransactionFilterOptions(
    accountId?: MaybeRefOrGetter<string | undefined>,
    accountScoped: MaybeRefOrGetter<boolean> = accountId !== undefined,
) {
    const query = useQuery({
        queryKey: computed(() => [
            'categories',
            'transaction-filter-options',
            toValue(accountScoped) ? (toValue(accountId) ?? 'unavailable') : 'all',
        ]),
        enabled: computed(() => !toValue(accountScoped) || Boolean(toValue(accountId))),
        queryFn: () =>
            repository.filterOptions(toValue(accountScoped) ? toValue(accountId) : undefined),
    });
    const options = computed(() => [
        { value: uncategorizedFilterValue, label: 'Sin categoría' },
        ...(query.data.value ?? []).map((category) => ({
            value: category.id,
            label: `${category.name} · ${category.accountName ?? 'Personal'}`,
        })),
    ]);
    return { query, options };
}
