import { computed, ref } from 'vue';

import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { createAccountInvitesRepository } from '../repositories/accountInvitesRepository';
import type {
  AccountInvite,
  AccountInviteListFilters,
  AccountInviteWritePayload,
} from '../schemas/accountInviteSchemas';

const accountInvitesRepository = createAccountInvitesRepository();

export function useAccountInvitesCrud() {
  const invitesState = usePaginatedCollection<
    AccountInvite,
    [string, AccountInviteListFilters | undefined]
  >({
    defaultPerPage: 20,
    loadPage: (options, accountId, filters) =>
      accountInvitesRepository.list(accountId, { ...options, filters }),
    resolveErrorMessage: resolveApiErrorMessage,
    loadErrorMessage: 'No fue posible cargar las invitaciones.',
    loadMoreErrorMessage: 'No fue posible cargar más invitaciones.',
  });
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasInvites = computed(() => invitesState.hasItems.value);
  const hasMoreInvites = computed(() => invitesState.hasMoreItems.value);
  const hasReachedEnd = computed(() => invitesState.hasReachedEnd.value);

  async function loadInvites(
    accountId: string,
    filters?: AccountInviteListFilters,
    options?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    await invitesState.load([accountId, filters], options);
  }

  async function loadMoreInvites(): Promise<void> {
    await invitesState.loadMore();
  }

  async function createInvite(payload: AccountInviteWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const invite = await accountInvitesRepository.create(payload);
      invitesState.prependItem(invite);
      return true;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible crear la invitación.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function updateInvite(
    inviteId: string,
    payload: AccountInviteWritePayload,
  ): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedInvite = await accountInvitesRepository.update(inviteId, payload);
      invitesState.replaceItem((invite) => invite.id === inviteId, updatedInvite);
      return true;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible actualizar la invitación.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function resendInvite(
    inviteId: string,
    payload: AccountInviteWritePayload,
  ): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedInvite = await accountInvitesRepository.resend(inviteId, payload);
      invitesState.replaceItem((invite) => invite.id === inviteId, updatedInvite);
      return true;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible reenviar la invitación.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function deleteInvite(inviteId: string, accountId?: string): Promise<boolean> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      await accountInvitesRepository.remove(inviteId, accountId);
      invitesState.removeItem((invite) => invite.id === inviteId);
      return true;
    } catch (error) {
      deleteError.value = resolveApiErrorMessage(error, 'No fue posible eliminar la invitación.');
      return false;
    } finally {
      isDeleting.value = false;
    }
  }

  function clearSaveError(): void {
    saveError.value = null;
  }

  function clearDeleteError(): void {
    deleteError.value = null;
  }

  return {
    invites: invitesState.items,
    hasInvites,
    hasMoreInvites,
    hasReachedEnd,
    isLoading: invitesState.isLoading,
    isLoadingMore: invitesState.isLoadingMore,
    isSaving,
    isDeleting,
    loadError: invitesState.loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadInvites,
    loadMoreInvites,
    createInvite,
    updateInvite,
    resendInvite,
    deleteInvite,
    perPage: invitesState.perPage,
  };
}
