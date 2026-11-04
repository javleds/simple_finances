import { shallowMount } from '@vue/test-utils';
import { expect, it } from 'vitest';
import TransactionListItem from './TransactionListItem.vue';

it('restores the receipts button when a parent count changes after deleting the last receipt', async () => {
    const wrapper = shallowMount(TransactionListItem, {
        props: {
            itemId: '1',
            attachmentsCount: 1,
            concept: 'Compra',
            amount: 20,
            type: 'expense',
            dateLabel: 'Hoy',
            creatorName: 'Ana',
        },
        global: { renderStubDefaultSlot: true },
    });
    window.dispatchEvent(
        new CustomEvent('transaction-attachments-count-updated', {
            detail: { transactionId: '1', count: 0 },
        }),
    );
    await wrapper.vm.$nextTick();
    expect(wrapper.find('[aria-label="Ver 1 comprobantes"]').exists()).toBe(false);
    await wrapper.setProps({ attachmentsCount: 2 });
    expect(wrapper.find('[aria-label="Ver 2 comprobantes"]').exists()).toBe(true);
    wrapper.unmount();
});
