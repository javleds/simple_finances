import { describe, expect, it } from 'vitest';
import {
    additionalFilterChips,
    additionalFiltersPayload,
    additionalFiltersQuery,
    readTransactionAdditionalFilters,
} from './transactionAdditionalFilters';

describe('category and receipt filters', () => {
    it('restores category IDs, uncategorized and explicit false from the URL', () => {
        const filters = readTransactionAdditionalFilters({
            category_ids: ['2,3', '2', 'invalid', '-1'],
            uncategorized: '1',
            has_attachments: '0',
        });
        expect(filters).toEqual({ categories: ['2', '3', 'uncategorized'], hasAttachments: false });
        expect(additionalFiltersQuery(filters.categories, filters.hasAttachments)).toEqual({
            category_ids: '2,3',
            uncategorized: '1',
            has_attachments: '0',
        });
        expect(additionalFiltersPayload(filters.categories, filters.hasAttachments)).toEqual({
            categoryIds: ['2', '3'],
            uncategorized: true,
            hasAttachments: false,
        });
    });
    it('omits the receipt condition for all and exposes removable chips with origin labels', () => {
        expect(additionalFiltersQuery([], null).has_attachments).toBeUndefined();
        const removed: string[] = [];
        const chips = additionalFilterChips(
            ['2', 'uncategorized'],
            false,
            [
                { value: '2', label: 'Comida · Casa' },
                { value: 'uncategorized', label: 'Sin categoría' },
            ],
            (id) => removed.push(id),
            () => removed.push('attachments'),
        );
        expect(chips.map((chip) => chip.label)).toEqual([
            'Comida · Casa',
            'Sin categoría',
            'Sin comprobantes',
        ]);
        chips.forEach((chip) => chip.remove());
        expect(removed).toEqual(['2', 'uncategorized', 'attachments']);
    });
});
