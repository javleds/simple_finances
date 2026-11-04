<script setup lang="ts">
import Message from 'primevue/message';
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { AccountGoal } from '@/modules/accounts/schemas/accountGoalSchemas';
import { AppModal, AppText } from '@/modules/shared/components';

const props = withDefaults(
    defineProps<{
        actions: ReadonlyArray<AppModalAction>;
        deleteError?: string | null;
        goal: AccountGoal | null;
        open: boolean;
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
        title="Eliminar meta"
        variant="danger"
        @action="$event === 'confirm-delete-goal' && emit('confirm')"
        @close="emit('close')"
    >
        <div class="space-y-3">
            <AppText v-if="props.goal">
                Vas a eliminar
                <strong>{{ props.goal.name }}</strong
                >.
            </AppText>
            <Message v-if="props.deleteError" severity="error">
                {{ props.deleteError }}
            </Message>
        </div>
    </AppModal>
</template>
