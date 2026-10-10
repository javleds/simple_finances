<script setup lang="ts">
import Message from 'primevue/message';
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { Account } from '@/modules/accounts/types';
import { AppModal, AppText } from '@/modules/shared/components';

const props = withDefaults(
    defineProps<{
        account: Account | null;
        actions: ReadonlyArray<AppModalAction>;
        deleteError?: string | null;
        mode?: 'delete' | 'leave';
        open: boolean;
    }>(),
    {
        deleteError: null,
        mode: 'delete',
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
        :title="props.mode === 'leave' ? 'Salir de la cuenta' : 'Eliminar cuenta'"
        variant="danger"
        @action="$event === 'confirm-delete-account' && emit('confirm')"
        @close="emit('close')"
    >
        <div class="space-y-3">
            <AppText v-if="props.account">
                <template v-if="props.mode === 'leave'">Vas a salir de </template>
                <template v-else>Vas a eliminar </template>
                <strong>{{ props.account.name }}</strong
                >.
            </AppText>
            <Message v-if="props.deleteError" severity="error">
                {{ props.deleteError }}
            </Message>
            <AppText v-if="props.mode === 'leave'" size="sm" tone="subtle">
                Solo puedes salir si no tienes porcentaje asignado, custodia ni reembolsos
                pendientes.
            </AppText>
            <AppText v-else size="sm" tone="subtle">
                La cuenta quedará inactiva y podrá consultarse desde el filtro de cuentas inactivas.
            </AppText>
        </div>
    </AppModal>
</template>
