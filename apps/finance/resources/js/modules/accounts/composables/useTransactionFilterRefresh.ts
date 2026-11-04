import { onBeforeUnmount, onMounted, watch, type Ref } from 'vue';

type FilterRefreshOptions = {
    categories: Ref<string[]>;
    hasAttachments: Ref<boolean | null>;
    isSaving: Ref<boolean>;
    isEditing: Ref<boolean>;
    isCreating: Ref<boolean>;
    reload: () => Promise<void>;
};
export function useTransactionFilterRefresh(options: FilterRefreshOptions) {
    let timer: ReturnType<typeof setTimeout> | undefined;
    function refreshAfterMutation(): void {
        if (
            (!options.categories.value.length && options.hasAttachments.value === null) ||
            timer !== undefined
        )
            return;
        timer = setTimeout(() => {
            timer = undefined;
            void options.reload();
        }, 0);
    }
    function attachmentChanged(): void {
        if (
            options.hasAttachments.value === null ||
            options.isSaving.value ||
            options.isEditing.value ||
            options.isCreating.value
        )
            return;
        refreshAfterMutation();
    }
    watch([options.isEditing, options.isCreating], ([editing, creating], previous) => {
        if (!editing && !creating && previous?.some(Boolean)) refreshAfterMutation();
    });
    onMounted(() =>
        window.addEventListener('transaction-attachments-count-updated', attachmentChanged),
    );
    onBeforeUnmount(() => {
        window.removeEventListener('transaction-attachments-count-updated', attachmentChanged);
        if (timer !== undefined) clearTimeout(timer);
    });
    return { refreshAfterMutation };
}
