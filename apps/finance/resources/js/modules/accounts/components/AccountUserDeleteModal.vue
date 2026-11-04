<script setup lang="ts">
import Message from 'primevue/message';
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { AccountMember } from '@/modules/accounts/types';
import { AppModal, AppText } from '@/modules/shared/components';

const props = withDefaults(
    defineProps<{
        actions: ReadonlyArray<AppModalAction>;
        deleteError?: string | null;
        open: boolean;
        selectedUser: AccountMember | null;
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
        title="Quitar usuario"
        variant="danger"
        @action="$event === 'confirm-delete-user' && emit('confirm')"
        @close="emit('close')"
    >
        <div class="space-y-3">
            <AppText v-if="props.selectedUser">
                Vas a quitar a <strong>{{ props.selectedUser.name }}</strong> de esta cuenta.
            </AppText>
            <Message v-if="props.deleteError" severity="error">
                {{ props.deleteError }}
            </Message>
        </div>
    </AppModal>
</template>
