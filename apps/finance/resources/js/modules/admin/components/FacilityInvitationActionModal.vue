<script setup lang="ts">
import Message from 'primevue/message';
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';
import { AppModal, AppText } from '@/modules/shared/components';

type PendingInvitationAction = 'accepted' | 'declined';

const props = withDefaults(
    defineProps<{
        actions: ReadonlyArray<AppModalAction>;
        invitation: AccountInvite | null;
        pendingAction: PendingInvitationAction | null;
        saveError?: string | null;
    }>(),
    {
        saveError: null,
    },
);

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();

function resolveAccountName(invitation: AccountInvite): string {
    return invitation.accountName ?? `Cuenta #${invitation.accountId}`;
}
</script>

<template>
    <AppModal
        :open="Boolean(props.invitation && props.pendingAction)"
        :actions="props.actions"
        :title="props.pendingAction === 'accepted' ? 'Aceptar invitación' : 'Rechazar invitación'"
        :variant="props.pendingAction === 'accepted' ? 'success' : 'danger'"
        @action="$event === 'confirm-invitation-action' && emit('confirm')"
        @close="emit('close')"
    >
        <div class="space-y-3">
            <AppText v-if="props.invitation">
                {{
                    props.pendingAction === 'accepted'
                        ? 'Vas a aceptar la invitación a'
                        : 'Vas a rechazar la invitación a'
                }}
                <strong>{{ resolveAccountName(props.invitation) }}</strong
                >.
            </AppText>
            <Message v-if="props.saveError" severity="error">
                {{ props.saveError }}
            </Message>
        </div>
    </AppModal>
</template>
