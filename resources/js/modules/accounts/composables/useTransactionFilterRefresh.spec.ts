import { mount } from '@vue/test-utils';
import { defineComponent, h, ref } from 'vue';
import { afterEach, expect, it, vi } from 'vitest';
import { useTransactionFilterRefresh } from './useTransactionFilterRefresh';
afterEach(() => vi.useRealTimers());
it('reloads after a filtered mutation and coalesces a simultaneous modal close', async () => {
    vi.useFakeTimers();
    const options = {
        categories: ref(['2']),
        hasAttachments: ref<boolean | null>(null),
        isSaving: ref(false),
        isEditing: ref(true),
        isCreating: ref(false),
        reload: vi.fn().mockResolvedValue(undefined),
    };
    let refresh!: ReturnType<typeof useTransactionFilterRefresh>;
    const wrapper = mount(
        defineComponent({
            setup() {
                refresh = useTransactionFilterRefresh(options);
                return () => h('div');
            },
        }),
    );
    refresh.refreshAfterMutation();
    options.isEditing.value = false;
    await wrapper.vm.$nextTick();
    await vi.runAllTimersAsync();
    expect(options.reload).toHaveBeenCalledTimes(1);
    wrapper.unmount();
});
it('defers receipt filter reload until editing closes to preserve the pending upload draft', async () => {
    vi.useFakeTimers();
    const options = {
        categories: ref<string[]>([]),
        hasAttachments: ref<boolean | null>(false),
        isSaving: ref(false),
        isEditing: ref(true),
        isCreating: ref(false),
        reload: vi.fn().mockResolvedValue(undefined),
    };
    const wrapper = mount(
        defineComponent({
            setup() {
                useTransactionFilterRefresh(options);
                return () => h('div');
            },
        }),
    );
    window.dispatchEvent(new CustomEvent('transaction-attachments-count-updated'));
    await vi.runAllTimersAsync();
    expect(options.reload).not.toHaveBeenCalled();
    options.isEditing.value = false;
    await wrapper.vm.$nextTick();
    await vi.runAllTimersAsync();
    expect(options.reload).toHaveBeenCalledTimes(1);
    window.dispatchEvent(new CustomEvent('transaction-attachments-count-updated'));
    await vi.runAllTimersAsync();
    expect(options.reload).toHaveBeenCalledTimes(2);
    wrapper.unmount();
});
