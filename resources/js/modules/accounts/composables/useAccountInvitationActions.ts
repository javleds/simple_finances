import type { ComputedRef, Ref } from 'vue';

import type {
    AccountInvite,
    AccountInviteListFilters,
    AccountInviteWritePayload,
} from '@/modules/accounts/schemas/accountInviteSchemas';

type LoadInvitesOptions = {
    reset?: boolean;
    perPage?: number;
};

type UseAccountInvitationActionsOptions = {
    accountId: ComputedRef<string>;
    isSaving: Ref<boolean>;
    activeFilters: ComputedRef<AccountInviteListFilters>;
    closeCreateInvitation: () => void;
    closeDeleteInvitation: () => void;
    closeEditInvitation: () => void;
    createInvite: (payload: AccountInviteWritePayload) => Promise<boolean>;
    deleteInvite: (invitationId: string, accountId?: string) => Promise<boolean>;
    hasReachedEnd: ComputedRef<boolean>;
    invitations: Ref<AccountInvite[]>;
    invitationsPerPage: ComputedRef<number>;
    isLoadingMore: Ref<boolean>;
    loadInvites: (
        accountId: string,
        filters?: AccountInviteListFilters,
        options?: LoadInvitesOptions,
    ) => Promise<void>;
    loadMoreInvites: () => Promise<void>;
    resendInvite: (invitationId: string, payload: AccountInviteWritePayload) => Promise<boolean>;
    selectedInvitation: ComputedRef<AccountInvite | null>;
    updateInvite: (invitationId: string, payload: AccountInviteWritePayload) => Promise<boolean>;
};

export function useAccountInvitationActions(options: UseAccountInvitationActionsOptions) {
    async function handleCreateInvitationSubmit(payload: AccountInviteWritePayload): Promise<void> {
        if (options.isSaving.value) return;
        const wasCreated = await options.createInvite(payload);

        if (wasCreated) {
            options.closeCreateInvitation();
        }
    }

    async function handleEditInvitationSubmit(payload: AccountInviteWritePayload): Promise<void> {
        if (options.isSaving.value) return;
        if (!options.selectedInvitation.value) {
            return;
        }

        const wasUpdated = await options.updateInvite(options.selectedInvitation.value.id, payload);

        if (wasUpdated) {
            options.closeEditInvitation();
        }
    }

    async function confirmDeleteInvitation(): Promise<void> {
        if (!options.selectedInvitation.value) {
            return;
        }

        const wasDeleted = await options.deleteInvite(
            options.selectedInvitation.value.id,
            options.accountId.value,
        );

        if (wasDeleted) {
            options.closeDeleteInvitation();
        }
    }

    async function handleResendInvitation(invitationId: string): Promise<void> {
        const invitation = options.invitations.value.find((item) => item.id === invitationId);

        if (!invitation || invitation.status !== 'declined') {
            return;
        }

        const wasResent = await options.resendInvite(invitation.id, {
            accountId: invitation.accountId,
            email: invitation.email,
            percentage: invitation.percentage,
            status: 'pending',
        });

        if (wasResent) {
            reloadInvitations();
        }
    }

    function reloadInvitations(): void {
        if (!options.accountId.value) {
            return;
        }

        void options.loadInvites(options.accountId.value, options.activeFilters.value, {
            reset: true,
            perPage: options.invitationsPerPage.value,
        });
    }

    function handleLoadMoreRetry(): void {
        void options.loadMoreInvites();
    }

    function infiniteStatusLabel(): string {
        if (options.isLoadingMore.value) {
            return 'Cargando más invitaciones...';
        }

        if (options.hasReachedEnd.value) {
            return 'Has llegado al final.';
        }

        return 'Sigue desplazándote para revisar más invitaciones conforme se amplíe la colaboración.';
    }

    return {
        confirmDeleteInvitation,
        handleCreateInvitationSubmit,
        handleEditInvitationSubmit,
        handleLoadMoreRetry,
        handleResendInvitation,
        infiniteStatusLabel,
        reloadInvitations,
    };
}
