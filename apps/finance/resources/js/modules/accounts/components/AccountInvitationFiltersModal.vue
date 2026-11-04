<script setup lang="ts">
import { ref, watch } from 'vue';
import type { AccountInviteStatus } from '@/modules/accounts/schemas/accountInviteSchemas';
import { AppFilterPanel, AppFilterOptions } from '@/modules/shared/components';

const props = defineProps<{
    open: boolean;
    selectedStatuses: AccountInviteStatus[];
    options: readonly { value: AccountInviteStatus; label: string }[];
}>();

const emit = defineEmits<{
    apply: [values: AccountInviteStatus[]];
    close: [];
}>();

const draft = ref<AccountInviteStatus[]>([]);

watch(
    () => props.open,
    (open) => {
        if (open) draft.value = [...props.selectedStatuses];
    },
    { immediate: true },
);

function apply(): void {
    emit('apply', [...draft.value]);
    emit('close');
}
</script>

<template>
    <AppFilterPanel
        id="account-invitation-filters"
        :open="props.open"
        @apply="apply"
        @clear="draft = []"
        @close="emit('close')"
    >
        <AppFilterOptions v-model="draft" label="Estatus" :options="props.options" />
    </AppFilterPanel>
</template>
