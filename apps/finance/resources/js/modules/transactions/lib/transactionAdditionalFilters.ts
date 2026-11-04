import type { ActiveFilter } from '@/modules/shared/types/filters';
import { parseQueryValues } from '@/modules/shared/lib/queryParams';
import type { TransactionAttachmentFilters } from '../types';

export const uncategorizedFilterValue = 'uncategorized';
export function readTransactionAdditionalFilters(query: Record<string, unknown>): {
    categories: string[];
    hasAttachments: boolean | null;
} {
    const categories = [
        ...new Set(
            parseQueryValues(query.category_ids, (value): value is string =>
                /^[1-9]\d*$/.test(value),
            ),
        ),
    ];
    if (query.uncategorized === '1') categories.push(uncategorizedFilterValue);
    return {
        categories,
        hasAttachments:
            query.has_attachments === '1' ? true : query.has_attachments === '0' ? false : null,
    };
}
export function additionalFiltersPayload(
    categories: readonly string[],
    hasAttachments: boolean | null,
): TransactionAttachmentFilters {
    return {
        categoryIds: categories.filter((id) => id !== uncategorizedFilterValue),
        uncategorized: categories.includes(uncategorizedFilterValue) || undefined,
        hasAttachments: hasAttachments ?? undefined,
    };
}
export function additionalFiltersQuery(
    categories: readonly string[],
    hasAttachments: boolean | null,
): Record<string, string | undefined> {
    const payload = additionalFiltersPayload(categories, hasAttachments);
    return {
        category_ids: payload.categoryIds?.length ? payload.categoryIds.join(',') : undefined,
        uncategorized: payload.uncategorized ? '1' : undefined,
        has_attachments: hasAttachments === null ? undefined : hasAttachments ? '1' : '0',
    };
}

export function additionalFilterChips(
    categories: readonly string[],
    hasAttachments: boolean | null,
    options: readonly { value: string; label: string }[],
    removeCategory: (id: string) => void,
    clearAttachments: () => void,
): ActiveFilter[] {
    const chips = categories.map((id) => ({
        key: `category-${id}`,
        label: options.find((option) => option.value === id)?.label ?? 'Categoría no disponible',
        remove: () => removeCategory(id),
    }));
    if (hasAttachments !== null)
        chips.push({
            key: 'has-attachments',
            label: hasAttachments ? 'Con comprobantes' : 'Sin comprobantes',
            remove: clearAttachments,
        });
    return chips;
}

export function validateCategorySelection(categories: readonly string[]): string | null {
    return categories.filter((id) => id !== uncategorizedFilterValue).length > 100
        ? 'Selecciona como máximo 100 categorías.'
        : null;
}
