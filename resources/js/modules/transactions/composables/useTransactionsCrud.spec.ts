import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useTransactionsCrud } from './useTransactionsCrud';
import type { CreatedTransactionResult, TransactionWritePayload } from '../types';

const mocks = vi.hoisted(() => ({ create: vi.fn(), update: vi.fn(), upload: vi.fn() }));
vi.mock('../repositories/transactionsRepository', () => ({
    createTransactionsRepository: () => ({ create: mocks.create, update: mocks.update }),
}));
vi.mock('../repositories/transactionAttachmentsRepository', () => ({
    transactionAttachmentsRepository: { upload: mocks.upload },
}));
function result(): CreatedTransactionResult {
    const transaction = {
        id: '123',
        attachmentsCount: 0,
    } as CreatedTransactionResult['transaction'];
    return {
        transaction,
        transactions: [transaction],
        meta: {} as CreatedTransactionResult['meta'],
    };
}
function payload(): TransactionWritePayload {
    return {
        accountId: '9',
        attachments: {
            transactionId: null,
            files: ['one.pdf', 'two.pdf'].map((name) => ({
                file: new File(['test'], name),
                uploadId: name,
                uploaded: false,
            })),
        },
    } as TransactionWritePayload;
}
function harness() {
    let state!: ReturnType<typeof useTransactionsCrud>;
    const wrapper = mount(
        defineComponent({
            setup() {
                state = useTransactionsCrud();
                return () => h('div');
            },
        }),
        {
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
            },
        },
    );
    return { state, wrapper };
}
beforeEach(() => {
    vi.resetAllMocks();
    mocks.create.mockResolvedValue(result());
    mocks.update.mockResolvedValue(result());
});
describe('transaction attachment uploads', () => {
    it('preserves a saved movement and retries only the missing upload', async () => {
        const { state, wrapper } = harness();
        const draft = payload();
        mocks.upload
            .mockResolvedValueOnce({})
            .mockRejectedValueOnce(new Error('Offline'))
            .mockResolvedValueOnce({});
        expect(await state.createTransaction(draft)).toBeNull();
        expect(draft.attachments?.transactionId).toBe('123');
        expect(draft.attachments?.files.map((entry) => entry.uploaded)).toEqual([true, false]);
        expect(state.saveError.value).toContain('El movimiento se guardó');
        expect(await state.createTransaction(draft)).not.toBeNull();
        expect(mocks.create).toHaveBeenCalledTimes(1);
        expect(mocks.upload.mock.calls.map((call) => call[2])).toEqual([
            'one.pdf',
            'two.pdf',
            'two.pdf',
        ]);
        expect(state.transactions.value).toHaveLength(1);
        wrapper.unmount();
    });
    it('continues without rejected files without creating another movement', async () => {
        const { state, wrapper } = harness();
        const draft = payload();
        mocks.upload.mockRejectedValue(new Error('Capacity exceeded'));
        await state.createTransaction(draft);
        draft.attachments!.files = [];
        expect(await state.createTransaction(draft)).not.toBeNull();
        expect(mocks.create).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });
    it('does not repeat financial updates after an upload failure', async () => {
        const { state, wrapper } = harness();
        const draft = payload();
        mocks.upload.mockRejectedValueOnce(new Error('Offline')).mockResolvedValue({});
        await state.updateTransaction('123', draft);
        expect(await state.updateTransaction('123', draft)).not.toBeNull();
        expect(mocks.update).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });
});
