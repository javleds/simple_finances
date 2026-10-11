<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useQuery, useQueryClient, type InfiniteData } from '@tanstack/vue-query';
import { AppButton, AppModal, AppText } from '@/modules/shared/components';
import { transactionAttachmentsRepository as repository } from '../repositories/transactionAttachmentsRepository';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { transactionFacilityQueryKeys } from '../queries/transactionFacilityQueries';
import type { AttachmentDraft, TransactionAttachment, Transaction } from '../types';

const props = withDefaults(
    defineProps<{
        transactionId?: string | null;
        draft?: AttachmentDraft;
        readOnly?: boolean;
        busy?: boolean;
    }>(),
    { transactionId: null, readOnly: false, busy: false },
);
const emit = defineEmits<{
    changed: [count: number];
    filesChange: [files: AttachmentDraft['files']];
}>();
const queryClient = useQueryClient();
const fileInput = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);
const expanded = ref(Boolean(props.transactionId));
const error = ref<string | null>(null);
const activeId = ref<string | null>(null);
const preview = ref<string | null>(null);
const previewName = ref('');
const visible = ref(typeof document === 'undefined' || document.visibilityState === 'visible');
const effectiveId = computed(() => props.draft?.transactionId ?? props.transactionId);
const config = useQuery({
    queryKey: ['transaction-attachments-config'],
    queryFn: repository.config,
    enabled: computed(() => !props.readOnly && expanded.value),
});
const attachments = useQuery({
    queryKey: computed(() => ['transaction-attachments', effectiveId.value]),
    enabled: computed(() => Boolean(effectiveId.value) && expanded.value && visible.value),
    queryFn: () => repository.list(effectiveId.value!),
    refetchInterval: (query) =>
        visible.value &&
        query.state.data?.data.some(
            (item) => item.status === 'pending' || item.status === 'processing',
        )
            ? (config.data.value?.poll_interval_ms ?? 5000)
            : false,
});
const localFiles = computed(() => props.draft?.files.filter((entry) => !entry.uploaded) ?? []);
const canManage = computed(
    () =>
        !props.readOnly && (!effectiveId.value || attachments.data.value?.meta.can_manage === true),
);
const labels = {
    pending: 'En espera',
    processing: 'Procesando',
    ready: 'Disponible',
    failed: 'No se pudo procesar',
};
const accept = computed(() =>
    config.data.value?.accepted_extensions
        .map((extension) => `.${extension.replace(/^\./, '')}`)
        .join(','),
);
watch(
    () => attachments.data.value?.meta.count,
    (count) => {
        if (count !== undefined) emit('changed', count);
    },
);
function updateVisibility(): void {
    visible.value = document.visibilityState === 'visible';
}
onMounted(() => document.addEventListener('visibilitychange', updateVisibility));
onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', updateVisibility);
    closePreview();
});
function size(bytes: number): string {
    return `${(bytes / 1024 / 1024).toFixed(1)} MiB`;
}
function addFiles(files: File[]): void {
    const limits = config.data.value;
    if (!limits || !props.draft || props.busy || !canManage.value) return;
    error.value = null;
    const existingCount = attachments.data.value?.meta.count ?? 0;
    let total =
        (attachments.data.value?.meta.total_bytes ?? 0) +
        localFiles.value.reduce((sum, entry) => sum + entry.file.size, 0);
    for (const file of files) {
        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
        const kind =
            extension === 'pdf' || extension === 'xml' || extension === 'zip' ? extension : 'image';
        const allowed = limits.accepted_extensions
            .map((value) => value.replace(/^\./, '').toLowerCase())
            .includes(extension);
        if (!allowed || file.size === 0 || file.size > limits.max_file_bytes[kind]) {
            error.value = `Revisa el formato y tamaño de ${file.name}.`;
            continue;
        }
        if (
            existingCount + localFiles.value.length >= limits.max_files ||
            total + file.size > limits.max_transaction_bytes
        ) {
            error.value =
                'Estos archivos superan el límite de comprobantes o espacio del movimiento.';
            break;
        }
        emit('filesChange', [
            ...props.draft.files,
            { file, uploadId: crypto.randomUUID(), uploaded: false },
        ]);
        total += file.size;
    }
}
function selected(event: Event): void {
    const input = event.target as HTMLInputElement;
    addFiles(Array.from(input.files ?? []));
    input.value = '';
}
function drop(event: DragEvent): void {
    addFiles(Array.from(event.dataTransfer?.files ?? []));
}
async function action(
    item: TransactionAttachment,
    operation: 'remove' | 'retry' | 'download' | 'preview',
): Promise<void> {
    if (!effectiveId.value || activeId.value || props.busy) return;
    if (operation === 'remove' && !window.confirm('¿Eliminar este comprobante?')) return;
    activeId.value = item.id;
    error.value = null;
    try {
        if (operation === 'remove') await repository.remove(effectiveId.value, item.id);
        if (operation === 'retry') await repository.retry(effectiveId.value, item.id);
        if (operation === 'download' || operation === 'preview') {
            const blob = await repository.download(effectiveId.value, item.id);
            const url = URL.createObjectURL(blob);
            if (operation === 'preview') {
                closePreview();
                preview.value = url;
                previewName.value = item.original_name;
            }
            if (operation === 'download') {
                const link = document.createElement('a');
                link.href = url;
                link.download =
                    item.mime_type === 'image/webp'
                        ? item.original_name.replace(/\.[^.]+$/, '') + '.webp'
                        : item.original_name;
                link.click();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            }
        }
        await attachments.refetch();
        await queryClient.invalidateQueries({ queryKey: transactionFacilityQueryKeys.all });
        const count = attachments.data.value?.meta.count;
        if (count !== undefined) {
            const update = (old: InfiniteData<{ items: Transaction[] }> | undefined) =>
                old
                    ? {
                          ...old,
                          pages: old.pages.map((page) => ({
                              ...page,
                              items: page.items.map((item) =>
                                  item.id === effectiveId.value
                                      ? { ...item, attachmentsCount: count }
                                      : item,
                              ),
                          })),
                      }
                    : old;
            queryClient.setQueriesData({ queryKey: transactionFacilityQueryKeys.all }, update);
            queryClient.setQueriesData(
                { queryKey: ['paginated-collection', 'No fue posible cargar las transacciones.'] },
                update,
            );
            window.dispatchEvent(
                new CustomEvent('transaction-attachments-count-updated', {
                    detail: { transactionId: effectiveId.value, count },
                }),
            );
        }
    } catch (cause) {
        error.value = resolveApiErrorMessage(cause, 'No fue posible completar la operación.');
    } finally {
        activeId.value = null;
    }
}
function closePreview(): void {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
}
</script>

<template>
    <section
        class="space-y-3 rounded-(--app-radius-control) border border-(--app-color-border) p-3"
        @dragover.prevent
        @drop.prevent="drop"
    >
        <AppButton
            type="button"
            variant="ghost"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
            >Comprobantes · Opcional
            <span v-if="localFiles.length || attachments.data.value?.meta.count"
                >({{ localFiles.length + (attachments.data.value?.meta.count ?? 0) }})</span
            ></AppButton
        >
        <div v-if="expanded" class="space-y-3">
            <AppText
                v-if="error || config.error.value || attachments.error.value"
                size="sm"
                role="alert"
                >{{ error ?? 'No fue posible cargar los comprobantes o sus límites.' }}</AppText
            >
            <AppButton
                v-if="config.error.value || attachments.error.value"
                type="button"
                variant="ghost"
                @click="
                    config.refetch();
                    attachments.refetch();
                "
                >Reintentar carga</AppButton
            >
            <AppText v-if="attachments.isPending.value && effectiveId" size="sm"
                >Cargando comprobantes…</AppText
            >
            <div v-if="draft && canManage && config.data.value" class="space-y-2">
                <AppText size="sm" tone="subtle"
                    >Hasta {{ config.data.value.max_files }} archivos y
                    {{ size(config.data.value.max_transaction_bytes) }} por movimiento. Imágenes:
                    {{ size(config.data.value.max_file_bytes.image) }}; PDF:
                    {{ size(config.data.value.max_file_bytes.pdf) }}; ZIP:
                    {{ size(config.data.value.max_file_bytes.zip) }}; XML:
                    {{ size(config.data.value.max_file_bytes.xml) }}.</AppText
                >
                <div class="flex flex-wrap gap-2">
                    <AppButton
                        type="button"
                        variant="outline"
                        :disabled="busy"
                        @click="fileInput?.click()"
                        >Elegir archivos</AppButton
                    >
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        :accept="accept"
                        :disabled="busy"
                        aria-label="Elegir archivos"
                        class="sr-only"
                        tabindex="-1"
                        @change="selected"
                    />
                    <AppButton
                        type="button"
                        variant="outline"
                        :disabled="busy"
                        class="sm:hidden"
                        @click="cameraInput?.click()"
                        >Tomar foto</AppButton
                    >
                    <input
                        ref="cameraInput"
                        type="file"
                        accept="image/*"
                        capture="environment"
                        :disabled="busy"
                        aria-label="Tomar foto"
                        class="sr-only"
                        tabindex="-1"
                        @change="selected"
                    />
                </div>
                <AppText size="sm" tone="subtle" class="hidden sm:block"
                    >También puedes arrastrar archivos aquí.</AppText
                >
            </div>
            <ul class="space-y-2">
                <li
                    v-for="entry in localFiles"
                    :key="entry.uploadId"
                    class="flex min-w-0 items-center justify-between gap-2 text-sm"
                >
                    <span class="min-w-0 wrap-anywhere"
                        >{{ entry.file.name }} · {{ size(entry.file.size) }} · Sin subir</span
                    >
                    <AppButton
                        type="button"
                        variant="ghost"
                        :disabled="busy"
                        @click="
                            emit('filesChange', draft?.files.filter((item) => item !== entry) ?? [])
                        "
                        >Quitar</AppButton
                    >
                </li>
                <li
                    v-for="item in attachments.data.value?.data ?? []"
                    :key="item.id"
                    class="space-y-1 border-t border-(--app-color-border) pt-2 text-sm"
                >
                    <p class="wrap-anywhere">
                        {{ item.original_name }} · {{ size(item.size_bytes) }} ·
                        {{ labels[item.status] }}
                    </p>
                    <p
                        v-if="item.status === 'pending' || item.status === 'processing'"
                        class="text-(--app-color-text-subtle)"
                        role="status"
                    >
                        Estamos preparando este archivo. Aparecerá aquí cuando esté listo.
                    </p>
                    <p v-if="item.error_message" role="alert">{{ item.error_message }}</p>
                    <div class="flex flex-wrap gap-2">
                        <AppButton
                            v-if="item.status === 'ready' && item.mime_type.startsWith('image/')"
                            type="button"
                            variant="ghost"
                            :disabled="Boolean(activeId)"
                            @click="action(item, 'preview')"
                            >Ver imagen</AppButton
                        >
                        <AppButton
                            v-if="item.status === 'ready'"
                            type="button"
                            variant="ghost"
                            :disabled="Boolean(activeId)"
                            @click="action(item, 'download')"
                            >Descargar</AppButton
                        >
                        <AppButton
                            v-if="item.can_retry && !readOnly"
                            type="button"
                            variant="ghost"
                            :disabled="Boolean(activeId) || busy"
                            @click="action(item, 'retry')"
                            >Reintentar</AppButton
                        >
                        <AppButton
                            v-if="item.can_manage && !readOnly"
                            type="button"
                            variant="ghost"
                            :disabled="Boolean(activeId) || busy"
                            @click="action(item, 'remove')"
                            >Eliminar</AppButton
                        >
                    </div>
                </li>
            </ul>
            <AppText
                v-if="!localFiles.length && attachments.data.value?.data.length === 0"
                size="sm"
                tone="subtle"
                >Sin comprobantes.</AppText
            >
        </div>
        <AppModal :open="Boolean(preview)" :title="previewName" @close="closePreview">
            <a
                v-if="preview"
                :href="preview"
                target="_blank"
                rel="noopener"
                aria-label="Ampliar imagen"
                ><img
                    :src="preview"
                    :alt="previewName"
                    class="mx-auto max-h-[70vh] max-w-full object-contain"
            /></a>
        </AppModal>
    </section>
</template>
