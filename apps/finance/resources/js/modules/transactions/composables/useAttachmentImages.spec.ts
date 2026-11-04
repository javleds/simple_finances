import { defineComponent, ref } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useAttachmentImages } from './useAttachmentImages';
import type { AttachmentDraft, TransactionAttachment } from '../types';

const download = vi.hoisted(() => vi.fn());
vi.mock('../repositories/transactionAttachmentsRepository', () => ({
    transactionAttachmentsRepository: { download },
}));
const attachment: TransactionAttachment = {
    id: '1',
    original_name: 'ticket.jpg',
    mime_type: 'image/webp',
    size_bytes: 100,
    status: 'ready',
    error_message: null,
    can_manage: false,
    can_retry: false,
    created_at: '',
};
function setup(initial: TransactionAttachment[] = [attachment], visible = true) {
    const transactionId = ref<string | null>('10');
    const enabled = ref(visible);
    const attachments = ref(initial);
    const localFiles = ref<AttachmentDraft['files']>([]);
    let images!: ReturnType<typeof useAttachmentImages>;
    const wrapper = mount(
        defineComponent({
            setup() {
                images = useAttachmentImages(transactionId, enabled, attachments, localFiles);
                return () => null;
            },
        }),
    );
    return { wrapper, images, enabled, transactionId, attachments, localFiles };
}
beforeEach(() => {
    vi.resetAllMocks();
    download.mockResolvedValue(new Blob(['image'], { type: 'image/webp' }));
    let id = 0;
    vi.stubGlobal(
        'URL',
        Object.assign(URL, {
            createObjectURL: vi.fn(() => `blob:${++id}`),
            revokeObjectURL: vi.fn(),
        }),
    );
});
describe('attachment image session', () => {
    it('reuses a single authenticated download for gallery, viewer and download', async () => {
        const { wrapper, images } = setup();
        const first = images.load('1');
        await flushPromises();
        expect(await first).toBe('blob:1');
        expect(await images.load('1')).toBe('blob:1');
        expect(download).toHaveBeenCalledExactlyOnceWith('10', '1');
        wrapper.unmount();
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:1');
    });
    it('does not download hidden, pending or non-image files', async () => {
        const state = setup([attachment], false);
        await flushPromises();
        expect(download).not.toHaveBeenCalled();
        state.attachments.value = [
            { ...attachment, status: 'processing' },
            { ...attachment, id: '2', mime_type: 'application/pdf' },
        ];
        state.enabled.value = true;
        await flushPromises();
        expect(download).not.toHaveBeenCalled();
        state.wrapper.unmount();
    });
    it('discards late responses when the session closes', async () => {
        let resolve!: (blob: Blob) => void;
        download.mockImplementation(
            () =>
                new Promise<Blob>((done) => {
                    resolve = done;
                }),
        );
        const state = setup();
        state.enabled.value = false;
        await flushPromises();
        resolve(new Blob(['late']));
        await flushPromises();
        expect(state.images.resources.value).toEqual({});
        expect(URL.createObjectURL).not.toHaveBeenCalled();
        state.wrapper.unmount();
    });
    it('clears old transaction URLs and ignores its outstanding request', async () => {
        const state = setup();
        await flushPromises();
        state.transactionId.value = '20';
        state.attachments.value = [];
        await flushPromises();
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:1');
        expect(state.images.resources.value).toEqual({});
        state.wrapper.unmount();
    });
    it('exposes individual errors and supports retry', async () => {
        download.mockRejectedValueOnce(new Error('network'));
        const state = setup();
        await flushPromises();
        expect(state.images.resources.value['1']?.failed).toBe(true);
        expect(await state.images.load('1')).toBe('blob:1');
        expect(state.images.resources.value['1']?.failed).toBe(false);
        state.attachments.value = [];
        await flushPromises();
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:1');
        state.wrapper.unmount();
    });
    it('previews supported local images and revokes removed selections', async () => {
        const state = setup([]);
        state.localFiles.value = ['jpg', 'webp', 'heic', 'pdf'].map((extension) => ({
            uploadId: extension,
            uploaded: false,
            file: new File(['image'], `photo.${extension}`),
        }));
        await flushPromises();
        expect(Object.keys(state.images.localUrls.value)).toEqual(['jpg', 'webp']);
        state.localFiles.value = [];
        await flushPromises();
        expect(URL.revokeObjectURL).toHaveBeenCalledTimes(2);
        state.wrapper.unmount();
    });
});
