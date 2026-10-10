import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import VirtualBalanceBreakdown from './VirtualBalanceBreakdown.vue';

const balance = { currentBalance: 10300, netCapital: 10000, observedYield: 300 };

describe('VirtualBalanceBreakdown', () => {
    it('separates contributed savings from recorded gains without counting the initial balance twice', () => {
        const wrapper = mount(VirtualBalanceBreakdown, {
            props: { balance: { ...balance, initialBalance: 10000 } as typeof balance },
        });
        expect(wrapper.findAll('dt').map((label) => label.text())).toEqual([
            'Ahorro neto',
            'Rendimiento registrado',
        ]);
        expect(wrapper.findAll('dd').map((value) => value.text())).toEqual([
            '$10,000.00',
            '+$300.00',
        ]);
        expect(wrapper.text()).not.toContain('Saldo sin desglose');
    });

    it.each([
        { currentBalance: 9800, observedYield: -200, expected: '-$200.00' },
        { currentBalance: 10000, observedYield: 0, expected: '$0.00' },
    ])(
        'shows the signed recorded yield as $expected',
        ({ currentBalance, observedYield, expected }) => {
            const wrapper = mount(VirtualBalanceBreakdown, {
                props: { balance: { ...balance, currentBalance, observedYield } },
            });
            expect(wrapper.findAll('dd')[1]?.text()).toBe(expected);
            expect(wrapper.text()).not.toContain('Saldo sin desglose');
        },
    );

    it.each([
        { currentBalance: 10400, expected: '$100.00' },
        { currentBalance: 10200, expected: '-$100.00' },
    ])(
        'discloses an unclassified difference of $expected without changing savings or yield',
        ({ currentBalance, expected }) => {
            const wrapper = mount(VirtualBalanceBreakdown, {
                props: { balance: { ...balance, currentBalance } },
            });
            expect(wrapper.text()).toContain(`Saldo sin desglose: ${expected}`);
            expect(wrapper.findAll('dd').map((value) => value.text())).toEqual([
                '$10,000.00',
                '+$300.00',
            ]);
        },
    );

    it('does not show floating point noise as an unclassified balance', () => {
        const wrapper = mount(VirtualBalanceBreakdown, {
            props: { balance: { currentBalance: 0.3, netCapital: 0.1, observedYield: 0.2 } },
        });
        expect(wrapper.text()).not.toContain('Saldo sin desglose');
    });
});
