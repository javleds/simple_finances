<script setup lang="ts">
import { MagnifyingGlassIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import AccountUserListItem from '@/modules/accounts/components/AccountUserListItem.vue';
import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';
import { ApiError } from '@/lib/api/apiClient';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import {
  AppButton,
  AppInput,
  AppModal,
  AppSectionBar,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const accountsRepository = createAccountsRepository();
const route = useRoute();
const defaultUsersPerPage = 20;

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

const accountId = computed(() =>
  typeof route.params.accountId === 'string' ? route.params.accountId : '',
);

const usersPerPage = computed(() => {
  const rawValue = typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultUsersPerPage;
  }

  return rawValue;
});

const usersState = usePaginatedCollection<AccountMember, [string]>({
  defaultPerPage: defaultUsersPerPage,
  loadPage: (options, nextAccountId) => accountsRepository.listUsers(nextAccountId, options),
  resolveErrorMessage,
  loadErrorMessage: 'No fue posible cargar los usuarios.',
  loadMoreErrorMessage: 'No fue posible cargar más usuarios.',
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !usersState.isLoading.value && !usersState.isLoadingMore.value && usersState.hasMoreItems.value),
  onIntersect: () => {
    void usersState.loadMore();
  },
});

const filteredUsers = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return usersState.items.value.filter((user) => {
    if (normalizedQuery.length === 0) {
      return true;
    }

    return (
      user.name.toLowerCase().includes(normalizedQuery) ||
      user.email.toLowerCase().includes(normalizedQuery)
    );
  });
});

const selectedUser = computed(() => {
  if (!selectedUserId.value) {
    return null;
  }

  return usersState.items.value.find((user) => user.id === selectedUserId.value) ?? null;
});

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
</script>

<template>
  <section class="space-y-4">
    <AppSectionBar
      title="Usuarios"
      description="Miembros reales compartidos en la cuenta y su porcentaje asignado."
    >
      <template #actions>
        <AppButton variant="primary" @click="openCreateUser">
          <PlusIcon class="h-4 w-4" />
          <span>Nuevo</span>
        </AppButton>
      </template>
    </AppSectionBar>

    <div class="relative flex-1">
      <div
        class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
      >
        <MagnifyingGlassIcon class="h-5 w-5" />
      </div>
      <AppInput
        id="user-search"
        v-model="searchTerm"
        type="search"
        placeholder="Buscar usuario por nombre o correo"
        class="pl-11"
      />
    </div>

    <section
      v-if="usersState.loadError.value && usersState.items.value.length > 0"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ usersState.loadError.value }}</AppText>
    </section>

    <section
      v-if="usersState.isLoading.value && usersState.items.value.length === 0"
      class="rounded-2xl border px-4 py-10 text-center"
    >
      <AppText>Cargando usuarios...</AppText>
    </section>

    <section
      v-else-if="usersState.loadError.value && usersState.items.value.length === 0"
      class="space-y-3 rounded-2xl border px-4 py-6 text-center"
    >
      <AppText>{{ usersState.loadError.value }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="secondary" @click="reloadUsers">Reintentar</AppButton>
      </div>
    </section>

    <section v-else class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">{{ filteredUsers.length }} usuarios visibles</AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <AccountUserListItem
          v-for="user in filteredUsers"
          :key="user.id"
          access-label="Cuenta compartida"
          :allocation-percentage="user.allocationPercentage"
          :email="user.email"
          :item-id="user.id"
          :name="user.name"
          pending-expenses="Usuario vinculado"
          role-label="Miembro"
          status="active"
          @delete="openDeleteUser"
          @edit="openEditUser"
        />

        <div
          v-if="filteredUsers.length === 0"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">No hay usuarios que coincidan con la búsqueda actual.</AppText>
        </div>

        <div
          ref="loadMoreSentinel"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">{{ infiniteStatusLabel() }}</AppText>
          <div
            v-if="usersState.loadError.value && usersState.items.value.length > 0"
            class="mt-3 flex justify-center"
          >
            <AppButton variant="secondary" @click="handleLoadMoreRetry">Reintentar</AppButton>
          </div>
        </div>
      </div>
    </section>

    <AppModal
      :open="isCreateUserOpen"
      :actions="[
        { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
      ]"
      title="Agregar usuario"
      variant="default"
      @close="closeCreateUser"
    >
      <div class="space-y-3">
        <AppText>
          La API permite adjuntar un usuario existente por `user_id`, pero este frontend todavía no
          cuenta con un catálogo global de usuarios para seleccionarlo desde aquí.
        </AppText>
        <AppText size="sm" tone="subtle">
          Por ahora, la incorporación de nuevos miembros sigue el flujo de invitaciones de la
          cuenta.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isEditUserOpen"
      :actions="editUserActions"
      title="Editar porcentaje"
      variant="default"
      @action="$event === 'submit-edit-user' && saveUserPercentage()"
      @close="closeEditUser"
    >
      <div class="space-y-5">
        <AppText v-if="selectedUser">
          Ajusta la participación de <strong>{{ selectedUser.name }}</strong> dentro de esta cuenta.
        </AppText>

        <AppInput
          id="account-user-percentage"
          v-model="editPercentage"
          label="Porcentaje"
          type="number"
          inputmode="decimal"
          min="0"
          max="100"
          step="0.01"
          placeholder="0.00"
          :error="
            saveError ??
            (editPercentage && !canSubmitPercentage()
              ? 'El porcentaje debe estar entre 0 y 100.'
              : undefined)
          "
          required
        />
      </div>
    </AppModal>

    <AppModal
      :open="isDeleteUserOpen"
      :actions="deleteUserActions"
      title="Quitar usuario"
      variant="danger"
      @action="$event === 'confirm-delete-user' && confirmDeleteUser()"
      @close="closeDeleteUser"
    >
      <div class="space-y-3">
        <AppText v-if="selectedUser">
          Vas a quitar a <strong>{{ selectedUser.name }}</strong> de esta cuenta.
        </AppText>
        <AppText v-if="deleteError" class="text-(--app-color-danger)!">{{ deleteError }}</AppText>
      </div>
    </AppModal>
  </section>
</template>
