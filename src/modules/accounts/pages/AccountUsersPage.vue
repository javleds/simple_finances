<script setup lang="ts">
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';

import AccountUserCreateModal from '@/modules/accounts/components/AccountUserCreateModal.vue';
import AccountUserDeleteModal from '@/modules/accounts/components/AccountUserDeleteModal.vue';
import AccountUserEditModal from '@/modules/accounts/components/AccountUserEditModal.vue';
import AccountUsersList from '@/modules/accounts/components/AccountUsersList.vue';
import AccountUsersSplitEditor from '@/modules/accounts/components/AccountUsersSplitEditor.vue';
import AccountUsersToolbar from '@/modules/accounts/components/AccountUsersToolbar.vue';
import { useAccountUserActions } from '@/modules/accounts/composables/useAccountUserActions';
import { useAccountUserFilters } from '@/modules/accounts/composables/useAccountUserFilters';
import { useAccountUserModalActions } from '@/modules/accounts/composables/useAccountUserModalActions';
import { useAccountUserModals } from '@/modules/accounts/composables/useAccountUserModals';
import { useAccountUsersCrud } from '@/modules/accounts/composables/useAccountUsersCrud';
import { useAccountUsersSplitDraft } from '@/modules/accounts/composables/useAccountUsersSplitDraft';
import type { AccountMember } from '@/modules/accounts/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import {
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';

const route = useRoute();
const defaultUsersPerPage = 20;

const emit = defineEmits<{
  accountUsersChange: [users: AccountMember[]];
}>();

const accountId = computed(() =>
  typeof route.params.accountId === 'string' ? route.params.accountId : '',
);

const usersPerPage = computed(() => {
  const rawValue =
    typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultUsersPerPage;
  }

  return rawValue;
});

const {
  activeFilters,
  searchTerm,
} = useAccountUserFilters({
  onChange: () => {
    void loadUsers();
  },
});

const {
  clearDeleteError,
  clearSaveError,
  deleteError,
  hasMoreUsers,
  hasReachedEnd,
  hasUsers,
  isDeleting,
  isLoading,
  isLoadingMore,
  isSaving,
  loadError,
  loadMoreUsers,
  loadUsers: loadAccountUsers,
  removeUser,
  saveError,
  setUsers,
  updateUserPercentage,
  users,
} = useAccountUsersCrud(activeFilters);

const {
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
} = useAccountUserModals({
  clearDeleteError,
  clearSaveError,
  users,
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreUsers.value),
  onIntersect: () => {
    void loadMoreUsers();
  },
});

const {
  applySplitDraft,
  canShowSplitEditor,
  hasLoadedEveryUserForSplit,
  hasSplitChanges,
  resetSplitDraft,
  splitDraft,
  splitUsers,
} = useAccountUsersSplitDraft({
  hasMoreUsers,
  onUsersChange: (nextUsers) => emit('accountUsersChange', nextUsers),
  setUsers,
  users,
});

const {
  canSubmitPercentage,
  confirmDeleteUser,
  handleLoadMoreRetry,
  infiniteStatusLabel,
  saveUserPercentage,
} = useAccountUserActions({
  accountId,
  closeDeleteUser,
  closeEditUser,
  editPercentage,
  hasReachedEnd,
  isLoadingMore,
  loadMoreUsers,
  removeUser,
  saveError,
  selectedUser,
  updateUserPercentage,
});

const {
  createUserActions,
  deleteUserActions,
  editUserActions,
} = useAccountUserModalActions({
  canSubmitPercentage,
  isDeleting,
  isSaving,
  selectedUser,
});

async function loadUsers(): Promise<void> {
  if (!accountId.value) {
    return;
  }

  await loadAccountUsers(accountId.value, {
    reset: true,
    perPage: usersPerPage.value,
  });
}

watch(
  [accountId, usersPerPage],
  () => {
    void loadUsers();
  },
  { immediate: true },
);

function reloadUsers(): void {
  void loadUsers();
}
</script>

<template>
  <section class="space-y-4">
    <AccountUsersToolbar v-model:search-term="searchTerm" @create="openCreateUser" />

    <AccountUsersSplitEditor
      v-model="splitDraft"
      :can-show="canShowSplitEditor"
      :has-changes="hasSplitChanges"
      :has-loaded-every-user="hasLoadedEveryUserForSplit"
      :users="splitUsers"
      @apply="applySplitDraft"
      @reset="resetSplitDraft"
    />

    <section
      v-if="loadError && hasUsers"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <AppListState
      :error="loadError"
      :has-items="hasUsers"
      :is-loading="isLoading"
      loading-label="Cargando usuarios..."
      @retry="reloadUsers"
    >
      <AccountUsersList
        :users="users"
        @delete="openDeleteUser"
        @edit="openEditUser"
      >
        <template #footer>
          <div ref="loadMoreSentinel">
            <AppLoadMoreFooter
              :label="infiniteStatusLabel()"
              :show-retry="Boolean(loadError && hasUsers)"
              @retry="handleLoadMoreRetry"
            />
          </div>
        </template>
      </AccountUsersList>
    </AppListState>

    <AccountUserCreateModal
      :open="isCreateUserOpen"
      :actions="createUserActions"
      @close="closeCreateUser"
    />

    <AccountUserEditModal
      v-model:edit-percentage="editPercentage"
      :open="isEditUserOpen"
      :actions="editUserActions"
      :can-submit="canSubmitPercentage()"
      :save-error="saveError"
      :selected-user="selectedUser"
      @close="closeEditUser"
      @save="saveUserPercentage"
    />

    <AccountUserDeleteModal
      :open="isDeleteUserOpen"
      :actions="deleteUserActions"
      :delete-error="deleteError"
      :selected-user="selectedUser"
      @close="closeDeleteUser"
      @confirm="confirmDeleteUser"
    />
  </section>
</template>
