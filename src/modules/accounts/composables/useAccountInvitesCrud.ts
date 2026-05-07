import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';

import { createAccountInvitesRepository } from '../repositories/accountInvitesRepository';
import type { AccountInvite, AccountInviteWritePayload } from '../schemas/accountInviteSchemas';

const accountInvitesRepository = createAccountInvitesRepository();

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export function useAccountInvitesCrud() {
  const invites = ref<AccountInvite[]>([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasInvites = computed(() => invites.value.length > 0);

  async function loadInvites(accountId: string): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
      invites.value = await accountInvitesRepository.list(accountId);
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar las invitaciones.');
    } finally {
      isLoading.value = false;
    }
  }

  async function createInvite(payload: AccountInviteWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const invite = await accountInvitesRepository.create(payload);
      invites.value = [invite, ...invites.value];
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible crear la invitación.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function updateInvite(inviteId: string, payload: AccountInviteWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedInvite = await accountInvitesRepository.update(inviteId, payload);
      invites.value = invites.value.map((invite) => (invite.id === inviteId ? updatedInvite : invite));
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible actualizar la invitación.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function deleteInvite(inviteId: string): Promise<boolean> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      await accountInvitesRepository.remove(inviteId);
      invites.value = invites.value.filter((invite) => invite.id !== inviteId);
      return true;
    } catch (error) {
      deleteError.value = resolveErrorMessage(error, 'No fue posible eliminar la invitación.');
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
    invites,
    hasInvites,
    isLoading,
    isSaving,
    isDeleting,
    loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadInvites,
    createInvite,
    updateInvite,
    deleteInvite,
  };
}
