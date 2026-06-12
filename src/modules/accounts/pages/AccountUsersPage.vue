<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import AccountUserCreateModal from '@/modules/accounts/components/AccountUserCreateModal.vue';
import AccountUserDeleteModal from '@/modules/accounts/components/AccountUserDeleteModal.vue';
import AccountUserEditModal from '@/modules/accounts/components/AccountUserEditModal.vue';
import AccountUsersList from '@/modules/accounts/components/AccountUsersList.vue';
import AccountUsersSplitEditor from '@/modules/accounts/components/AccountUsersSplitEditor.vue';
import AccountUsersToolbar from '@/modules/accounts/components/AccountUsersToolbar.vue';
import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';
import type { AccountMember } from '@/modules/accounts/types';
import { ApiError } from '@/lib/api/apiClient';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import { areQueriesEqual } from '@/modules/shared/lib/queryParams';
import {
  AppButton,
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';

const accountsRepository = createAccountsRepository();
const route = useRoute();
const router = useRouter();
const defaultUsersPerPage = 20;

const emit = defineEmits<{
  accountUsersChange: [users: AccountMember[]];
}>();

const searchTerm = ref('');
const isSaving = ref(false);
const isDeleting = ref(false);
const saveError = ref<string | null>(null);
const deleteError = ref<string | null>(null);
const isCreateUserOpen = ref(false);
const isEditUserOpen = ref(false);
const isDeleteUserOpen = ref(false);
const selectedUserId = ref<string | null>(null);
const editPercentage = ref('');
const splitDraft = ref<Record<string, number>>({});

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

const usersState = usePaginatedCollection<AccountMember, [string]>({
  defaultPerPage: defaultUsersPerPage,
  loadPage: (options, nextAccountId) =>
    accountsRepository.listUsers(nextAccountId, {
      ...options,
      filters: {
        search: searchTerm.value.trim() || undefined,
      },
    }),
  resolveErrorMessage,
  loadErrorMessage: 'No fue posible cargar los usuarios.',
  loadMoreErrorMessage: 'No fue posible cargar más usuarios.',
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(
    () =>
      !usersState.isLoading.value &&
      !usersState.isLoadingMore.value &&
      usersState.hasMoreItems.value,
  ),
  onIntersect: () => {
    void usersState.loadMore();
  },
});

const selectedUser = computed(() => {
  if (!selectedUserId.value) {
    return null;
  }

  return usersState.items.value.find((user) => user.id === selectedUserId.value) ?? null;
});

const splitUsers = computed(() =>
  usersState.items.value.map((user) => ({
    id: user.id,
    name: user.name,
  })),
);
const canShowSplitEditor = computed(() => splitUsers.value.length > 1);

const hasLoadedEveryUserForSplit = computed(() => !usersState.hasMoreItems.value);

const hasSplitChanges = computed(
  () =>
    !areAllocationRecordsEqual(splitDraft.value, createAllocationRecord(usersState.items.value)),
);

const editUserActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-edit-user',
    label: isSaving.value ? 'Guardando...' : 'Guardar porcentaje',
    tone: 'primary' as const,
    type: 'button' as const,
    disabled: !canSubmitPercentage() || isSaving.value,
  },
]);

const deleteUserActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-delete-user',
    label: isDeleting.value ? 'Eliminando...' : 'Quitar usuario',
    tone: 'primary' as const,
    disabled: !selectedUser.value || isDeleting.value,
  },
]);

const createUserActions = [
  { key: 'close', label: 'Cerrar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
];

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

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
  const percentage = parsePercentage(editPercentage.value);
  return percentage !== null && percentage >= 0 && percentage <= 100;
}

async function loadUsers(): Promise<void> {
  if (!accountId.value) {
    return;
  }

  await usersState.load([accountId.value], {
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

watch(
  () => route.query.search,
  (nextSearch) => {
    searchTerm.value = typeof nextSearch === 'string' ? nextSearch : '';
  },
  { immediate: true },
);

watch(searchTerm, () => {
  const nextQuery = {
    ...route.query,
    search: searchTerm.value.trim() || undefined,
  };

  if (areQueriesEqual(route.query, nextQuery)) {
    return;
  }

  void router.replace({ query: nextQuery });
  void loadUsers();
});

watch(
  () => usersState.items.value,
  (nextUsers) => {
    const nextRecord = createAllocationRecord(nextUsers);

    if (!hasSplitChanges.value) {
      splitDraft.value = nextRecord;
    } else {
      splitDraft.value = mergeAllocationRecords(splitDraft.value, nextRecord);
    }

    emit('accountUsersChange', nextUsers);
  },
  { immediate: true, deep: true },
);

function openCreateUser(): void {
  isCreateUserOpen.value = true;
}

function closeCreateUser(): void {
  isCreateUserOpen.value = false;
}

function openEditUser(userId: string): void {
  saveError.value = null;
  selectedUserId.value = userId;
  editPercentage.value = selectedUser.value ? String(selectedUser.value.allocationPercentage) : '';
  isEditUserOpen.value = true;
}

function closeEditUser(): void {
  isEditUserOpen.value = false;
  selectedUserId.value = null;
  editPercentage.value = '';
  saveError.value = null;
}

function openDeleteUser(userId: string): void {
  deleteError.value = null;
  selectedUserId.value = userId;
  isDeleteUserOpen.value = true;
}

function closeDeleteUser(): void {
  isDeleteUserOpen.value = false;
  selectedUserId.value = null;
  deleteError.value = null;
}

async function saveUserPercentage(): Promise<void> {
  if (!accountId.value || !selectedUser.value) {
    return;
  }

  const percentage = parsePercentage(editPercentage.value);

  if (percentage === null) {
    saveError.value = 'El porcentaje debe ser un número válido.';
    return;
  }

  isSaving.value = true;
  saveError.value = null;

  try {
    const updatedUser = await accountsRepository.updateUserPercentage(
      accountId.value,
      selectedUser.value.id,
      percentage,
    );

    usersState.replaceItem((user) => user.id === updatedUser.id, updatedUser);
    closeEditUser();
  } catch (error) {
    saveError.value = resolveErrorMessage(error, 'No fue posible actualizar el porcentaje.');
  } finally {
    isSaving.value = false;
  }
}

async function confirmDeleteUser(): Promise<void> {
  if (!accountId.value || !selectedUser.value) {
    return;
  }

  isDeleting.value = true;
  deleteError.value = null;

  try {
    await accountsRepository.removeUser(accountId.value, selectedUser.value.id);
    usersState.removeItem((user) => user.id === selectedUser.value?.id);
    closeDeleteUser();
  } catch (error) {
    deleteError.value = resolveErrorMessage(error, 'No fue posible quitar al usuario.');
  } finally {
    isDeleting.value = false;
  }
}

function reloadUsers(): void {
  void loadUsers();
}

function handleLoadMoreRetry(): void {
  void usersState.loadMore();
}

function infiniteStatusLabel(): string {
  if (usersState.isLoadingMore.value) {
    return 'Cargando más usuarios...';
  }

  if (usersState.hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para revisar más miembros conforme crezca la colaboración de la cuenta.';
}

function createAllocationRecord(users: ReadonlyArray<AccountMember>): Record<string, number> {
  return users.reduce<Record<string, number>>((accumulator, user) => {
    accumulator[user.id] = Number((user.allocationPercentage ?? 0).toFixed(2));
    return accumulator;
  }, {});
}

function mergeAllocationRecords(
  currentRecord: Record<string, number>,
  nextRecord: Record<string, number>,
): Record<string, number> {
  return Object.keys(nextRecord).reduce<Record<string, number>>((accumulator, userId) => {
    accumulator[userId] = currentRecord[userId] ?? nextRecord[userId] ?? 0;
    return accumulator;
  }, {});
}

function areAllocationRecordsEqual(
  left: Record<string, number>,
  right: Record<string, number>,
): boolean {
  const allKeys = new Set([...Object.keys(left), ...Object.keys(right)]);

  return [...allKeys].every((key) => {
    const leftValue = Number((left[key] ?? 0).toFixed(2));
    const rightValue = Number((right[key] ?? 0).toFixed(2));
    return leftValue === rightValue;
  });
}

function applySplitDraft(): void {
  usersState.setItems(
    usersState.items.value.map((user) => ({
      ...user,
      allocationPercentage: splitDraft.value[user.id] ?? user.allocationPercentage,
    })),
  );
}

function resetSplitDraft(): void {
  splitDraft.value = createAllocationRecord(usersState.items.value);
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
      v-if="usersState.loadError.value && usersState.items.value.length > 0"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ usersState.loadError.value }}</AppText>
    </section>

    <AppListState
      :error="usersState.loadError.value"
      :has-items="usersState.items.value.length > 0"
      :is-loading="usersState.isLoading.value"
      loading-label="Cargando usuarios..."
      @retry="reloadUsers"
    >
      <AccountUsersList
        :users="usersState.items.value"
        @delete="openDeleteUser"
        @edit="openEditUser"
      >
        <template #footer>
          <div ref="loadMoreSentinel">
            <AppLoadMoreFooter
              :label="infiniteStatusLabel()"
              :show-retry="Boolean(usersState.loadError.value && usersState.items.value.length > 0)"
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
