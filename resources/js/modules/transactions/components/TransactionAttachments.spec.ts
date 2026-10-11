import { flushPromises, mount } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TransactionAttachments from './TransactionAttachments.vue';
import type { AttachmentDraft } from '../types';

const mocks = vi.hoisted(() => ({ config: vi.fn(), list: vi.fn() }));
vi.mock('../repositories/transactionAttachmentsRepository', () => ({
    transactionAttachmentsRepository: mocks,
}));
const limits = {
    max_files: 2,
    max_file_bytes: { image: 100, pdf: 100, xml: 10, zip: 100 },
    max_transaction_bytes: 150,
    accepted_extensions: ['pdf', 'xml', 'jpg'],
    poll_interval_ms: 5000,
};
function render(props: Partial<InstanceType<typeof TransactionAttachments>['$props']> = {}) {
    return mount(TransactionAttachments, {
        props: {
            ...props,
            onFilesChange: (files: AttachmentDraft['files']) => {
                if (props.draft) props.draft.files = files;
            },
        },
        global: {
            plugins: [
                [
                    VueQueryPlugin,
                    {
                        queryClient: new QueryClient({
                            defaultOptions: { queries: { retry: false } },
                        }),
                    },
                ],
            ],
            stubs: { AppModal: true },
        },
    });
}
async function select(wrapper: ReturnType<typeof render>, files: File[]): Promise<void> {
    const input = wrapper.get('input[multiple]');
    Object.defineProperty(input.element, 'files', { configurable: true, value: files });
    await input.trigger('change');
    await flushPromises();
}
beforeEach(() => {
    vi.resetAllMocks();
    mocks.config.mockResolvedValue(limits);
});
describe('transaction receipts', () => {
    it('starts collapsed and validates selected formats and sizes against server limits', async () => {
        const draft = reactive<AttachmentDraft>({ files: [], transactionId: null });
        const wrapper = render({ draft });
        expect(wrapper.find('input[type=file]').exists()).toBe(false);
        await wrapper.get('button').trigger('click');
        await flushPromises();
        await select(wrapper, [
            new File(['receipt'], 'receipt.pdf'),
            new File(['bad'], 'script.exe'),
        ]);
        expect(draft.files).toHaveLength(1);
        expect(wrapper.text()).toContain('Revisa el formato y tamaño de script.exe');
        await select(wrapper, [new File(['x'.repeat(11)], 'invoice.xml')]);
        expect(draft.files).toHaveLength(1);
        expect(wrapper.text()).toContain('invoice.xml');
        wrapper.unmount();
    });
    it('counts current selections toward the per-transaction limit', async () => {
        const draft = reactive<AttachmentDraft>({ files: [], transactionId: null });
        const wrapper = render({ draft });
        await wrapper.get('button').trigger('click');
        await flushPromises();
        await select(
            wrapper,
            ['one.pdf', 'two.pdf', 'three.pdf'].map((name) => new File(['receipt'], name)),
        );
        expect(draft.files).toHaveLength(2);
        expect(wrapper.text()).toContain('superan el límite');
        wrapper.unmount();
    });
    it('shows pending receipts without exposing download actions or URLs', async () => {
        mocks.list.mockResolvedValue({
            data: [
                {
                    id: '1',
                    original_name: 'ticket.jpg',
                    mime_type: 'image/webp',
                    size_bytes: 10,
                    status: 'processing',
                    error_message: null,
                    can_manage: true,
                    can_retry: false,
                    created_at: '2026-10-10',
                },
            ],
            meta: { can_manage: true, count: 1, total_bytes: 10 },
        });
        const wrapper = render({ transactionId: '10', readOnly: true });
        await flushPromises();
        expect(wrapper.text()).toContain('Estamos preparando este archivo');
        expect(wrapper.text()).not.toContain('Descargar');
        expect(wrapper.text()).not.toContain('Eliminar');
        expect(wrapper.find('img').exists()).toBe(false);
        wrapper.unmount();
    });
});
