<script setup lang="ts">
import Message from 'primevue/message';
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { Transaction } from '@/modules/transactions/types';
import { AppModal, AppText } from '@/modules/shared/components';

const props = withDefaults(
    defineProps<{
        actions: ReadonlyArray<AppModalAction>;
        deleteError?: string | null;
        open: boolean;
        transaction: Transaction | null;
    }>(),
    {
        deleteError: null,
    },
);

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();
</script>

<template>
    <AppModal
        :open="props.open"
        :actions="props.actions"
        title="Eliminar transacción"
        variant="danger"
        @action="$event === 'confirm-delete-transaction' && emit('confirm')"
        @close="emit('close')"
    >
        <div class="space-y-3">
            <AppText v-if="props.transaction">
                Vas a eliminar
                <strong>{{ props.transaction.concept }}</strong
                >.
            </AppText>
            <Message v-if="props.deleteError" severity="error">
                {{ props.deleteError }}
            </Message>
        </div>
    </AppModal>
</template>
