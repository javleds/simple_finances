import type { ComputedRef, Ref } from 'vue';

import type { AccountMember } from '@/modules/accounts/types';

type UseAccountUserActionsOptions = {
  accountId: ComputedRef<string>;
  closeDeleteUser: () => void;
  closeEditUser: () => void;
  editPercentage: Ref<string>;
  hasReachedEnd: Ref<boolean>;
  isLoadingMore: Ref<boolean>;
  loadMoreUsers: () => Promise<void>;
  removeUser: (accountId: string, userId: string) => Promise<boolean>;
  saveError: Ref<string | null>;
  selectedUser: ComputedRef<AccountMember | null>;
  updateUserPercentage: (accountId: string, userId: string, percentage: number) => Promise<boolean>;
};

export function useAccountUserActions(options: UseAccountUserActionsOptions) {
  function parsePercentage(value: string): number | null {
    const normalizedValue = value.trim();

    if (!normalizedValue) {
      return null;
    }

    const parsedValue = Number(normalizedValue);

    if (!Number.isFinite(parsedValue)) {
      return null;
    }

    return parsedValue;
  }

  function canSubmitPercentage(): boolean {
    const percentage = parsePercentage(options.editPercentage.value);
    return percentage !== null && percentage >= 0 && percentage <= 100;
  }

  async function saveUserPercentage(): Promise<void> {
    if (!options.accountId.value || !options.selectedUser.value) {
      return;
    }

    const percentage = parsePercentage(options.editPercentage.value);

    if (percentage === null) {
      options.saveError.value = 'El porcentaje debe ser un número válido.';
      return;
    }

    const wasUpdated = await options.updateUserPercentage(
      options.accountId.value,
      options.selectedUser.value.id,
      percentage,
    );

    if (wasUpdated) {
      options.closeEditUser();
    }
  }

  async function confirmDeleteUser(): Promise<void> {
    if (!options.accountId.value || !options.selectedUser.value) {
      return;
    }

    const wasDeleted = await options.removeUser(
      options.accountId.value,
      options.selectedUser.value.id,
    );

    if (wasDeleted) {
      options.closeDeleteUser();
    }
  }

  function handleLoadMoreRetry(): void {
    void options.loadMoreUsers();
  }

  function infiniteStatusLabel(): string {
    if (options.isLoadingMore.value) {
      return 'Cargando más usuarios...';
    }

    if (options.hasReachedEnd.value) {
      return 'Has llegado al final.';
    }

    return 'Sigue desplazándote para revisar más miembros conforme crezca la colaboración de la cuenta.';
  }

  return {
    canSubmitPercentage,
    confirmDeleteUser,
    handleLoadMoreRetry,
    infiniteStatusLabel,
    saveUserPercentage,
  };
}
