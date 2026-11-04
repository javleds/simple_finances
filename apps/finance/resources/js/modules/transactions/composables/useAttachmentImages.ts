import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue';
import { transactionAttachmentsRepository } from '../repositories/transactionAttachmentsRepository';
import type { AttachmentDraft, TransactionAttachment } from '../types';

type ImageResource = {
    url: string;
    loading: boolean;
    failed: boolean;
};

export function useAttachmentImages(
    transactionId: Readonly<Ref<string | null | undefined>>,
    enabled: Readonly<Ref<boolean>>,
    attachments: Readonly<Ref<TransactionAttachment[]>>,
    localFiles: Readonly<Ref<AttachmentDraft['files']>>,
) {
    const resources = ref<Record<string, ImageResource>>({});
    const localUrls = ref<Record<string, string>>({});
    const pending = new Map<string, Promise<string | null>>();
    const readyImages = computed(() =>
        attachments.value.filter(
            (item) => item.status === 'ready' && item.mime_type.startsWith('image/'),
        ),
    );
    let session = 0;

    function clear(): void {
        session += 1;
        Object.values(resources.value).forEach((item) => {
            if (item.url) URL.revokeObjectURL(item.url);
        });
        Object.values(localUrls.value).forEach((url) => URL.revokeObjectURL(url));
        resources.value = {};
        localUrls.value = {};
        pending.clear();
    }

    async function load(id: string): Promise<string | null> {
        if (!enabled.value || !transactionId.value) return null;
        if (resources.value[id]?.url) return resources.value[id].url;
        if (pending.has(id)) return pending.get(id)!;
        const currentSession = session;
        const currentTransactionId = transactionId.value;
        resources.value[id] = { url: '', loading: true, failed: false };
        const request = (async (): Promise<string | null> => {
            try {
                const blob = await transactionAttachmentsRepository.download(
                    currentTransactionId,
                    id,
                );
                if (session !== currentSession || !readyImages.value.some((item) => item.id === id))
                    return null;
                const url = URL.createObjectURL(blob);
                resources.value[id] = { url, loading: false, failed: false };
                return url;
            } catch {
                if (
                    session === currentSession &&
                    readyImages.value.some((item) => item.id === id)
                ) {
                    resources.value[id] = { url: '', loading: false, failed: true };
                }
                return null;
            } finally {
                if (session === currentSession) pending.delete(id);
            }
        })();
        pending.set(id, request);
        return request;
    }

    function sync(): void {
        if (!enabled.value) return;
        const ids = new Set(readyImages.value.map((item) => item.id));
        for (const [id, resource] of Object.entries(resources.value)) {
            if (ids.has(id)) continue;
            if (resource.url) URL.revokeObjectURL(resource.url);
            delete resources.value[id];
        }
        for (const item of readyImages.value) {
            if (!resources.value[item.id]) void load(item.id);
        }
        const localIds = new Set<string>();
        for (const entry of localFiles.value) {
            if (!/\.(jpe?g|png|webp)$/i.test(entry.file.name)) continue;
            localIds.add(entry.uploadId);
            if (!localUrls.value[entry.uploadId]) {
                localUrls.value[entry.uploadId] = URL.createObjectURL(entry.file);
            }
        }
        for (const [id, url] of Object.entries(localUrls.value)) {
            if (localIds.has(id)) continue;
            URL.revokeObjectURL(url);
            delete localUrls.value[id];
        }
    }

    watch([transactionId, enabled], () => {
        clear();
        sync();
    });
    watch([readyImages, localFiles], sync, { immediate: true });
    onBeforeUnmount(clear);

    return { resources, localUrls, readyImages, load };
}
