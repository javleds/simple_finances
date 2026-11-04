import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import AccountTransactionsActivity from './AccountTransactionsActivity.vue';

describe('account transaction filters placement', () => {
    it('renders active filters after the search toolbar and before the list', () => {
        const wrapper = mount(AccountTransactionsActivity, {
            props: {
                activeFilterCount: 1,
                filtersOpen: false,
                currentUserId: '1',
                hasTransactions: true,
                infiniteStatusLabel: '',
                isLoading: false,
                searchTerm: '',
                showLoadMoreRetry: false,
                transactions: [],
            },
            slots: { filters: '<div data-position="filters">Ingreso</div>' },
            global: {
                stubs: {
                    AccountTransactionsToolbar: { template: '<div data-position="search" />' },
                    AccountTransactionsList: { template: '<div data-position="list" />' },
                    AppListState: { template: '<div><slot /></div>' },
                    AppLoadMoreFooter: true,
                },
            },
        });

        const elements = wrapper.findAll('[data-position]');
        expect(elements.map((element) => element.attributes('data-position'))).toEqual([
            'search',
            'filters',
            'list',
        ]);
    });
});
