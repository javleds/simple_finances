import { computed, ref, type Ref } from 'vue';

import type { AccountMember } from '@/modules/accounts/types';

type UseAccountUserModalsOptions = {
  clearDeleteError: () => void;
  clearSaveError: () => void;
  users: Ref<AccountMember[]>;
};

export function useAccountUserModals(options: UseAccountUserModalsOptions) {
  const isCreateUserOpen = ref(false);
  const isEditUserOpen = ref(false);
  const isDeleteUserOpen = ref(false);
  const selectedUserId = ref<string | null>(null);
  const editPercentage = ref('');

  const selectedUser = computed(() => {
    if (!selectedUserId.value) {
      return null;
    }

    return options.users.value.find((user) => user.id === selectedUserId.value) ?? null;
  });

  function openCreateUser(): void {
    isCreateUserOpen.value = true;
  }

  function closeCreateUser(): void {
    isCreateUserOpen.value = false;
  }

  function openEditUser(userId: string): void {
    options.clearSaveError();
    selectedUserId.value = userId;
    editPercentage.value = selectedUser.value ? String(selectedUser.value.allocationPercentage) : '';
    isEditUserOpen.value = true;
  }

  function closeEditUser(): void {
    isEditUserOpen.value = false;
    selectedUserId.value = null;
    editPercentage.value = '';
    options.clearSaveError();
  }

  function openDeleteUser(userId: string): void {
    options.clearDeleteError();
    selectedUserId.value = userId;
    isDeleteUserOpen.value = true;
  }

  function closeDeleteUser(): void {
    isDeleteUserOpen.value = false;
    selectedUserId.value = null;
    options.clearDeleteError();
  }

  return {
    closeCreateUser,
    closeDeleteUser,
    closeEditUser,
    editPercentage,
    isCreateUserOpen,
    isDeleteUserOpen,
    isEditUserOpen,
    openCreateUser,
    openDeleteUser,
    openEditUser,
    selectedUser,
  };
}
