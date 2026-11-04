<script setup lang="ts">
import Message from 'primevue/message';
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { Subscription } from '@/modules/subscriptions/types';
import { AppModal, AppText } from '@/modules/shared/components';

const props = withDefaults(
    defineProps<{
        actions: ReadonlyArray<AppModalAction>;
        deleteError?: string | null;
        open: boolean;
        subscription: Subscription | null;
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
        title="Eliminar suscripción"
        variant="danger"
        @action="$event === 'confirm-delete-subscription' && emit('confirm')"
        @close="emit('close')"
    >
        <div class="space-y-3">
            <AppText v-if="props.subscription">
                Vas a eliminar
                <strong>{{ props.subscription.name }}</strong
                >.
            </AppText>
            <Message v-if="props.deleteError" severity="error">
                {{ props.deleteError }}
            </Message>
        </div>
    </AppModal>
</template>
