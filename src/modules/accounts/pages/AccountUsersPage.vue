<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';

import AccountUserListItem from '@/modules/accounts/components/AccountUserListItem.vue';
import { findAccountById } from '@/modules/accounts/data/accounts';
import {
  AppButton,
  AppCard,
  AppIconButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type UserRole = 'admin' | 'reconciliation' | 'approver';
type UserAccess = 'full' | 'limited' | 'read-move';
type UserStatus = 'active' | 'invited';

const route = useRoute();
const searchTerm = ref('');
const isFiltersOpen = ref(false);
const isCreateUserOpen = ref(false);
const isEditUserOpen = ref(false);
const isDeleteUserOpen = ref(false);
const selectedRoles = ref<UserRole[]>([]);
const selectedAccesses = ref<UserAccess[]>([]);
const selectedUserId = ref<string | null>(null);

const account = computed(() => {
  const accountId = typeof route.params.accountId === 'string' ? route.params.accountId : '';
  return findAccountById(accountId);
});

const userRoleOptions = [
  { value: 'admin', label: 'Administrador' },
  { value: 'reconciliation', label: 'Conciliación' },
  { value: 'approver', label: 'Aprobador' },
] as const;

const userAccessOptions = [
  { value: 'full', label: 'Acceso total' },
  { value: 'read-move', label: 'Lectura y movimientos' },
  { value: 'limited', label: 'Permisos limitados' },
] as const;

const accountUsers = computed(() => {
  const members = account.value?.users ?? [];

  return members.map((member, index) => {
    const roleMap: UserRole[] = ['admin', 'reconciliation', 'approver'];
    const accessMap: UserAccess[] = ['full', 'read-move', 'limited'];
    const role = roleMap[index % roleMap.length] ?? 'approver';
    const access = accessMap[index % accessMap.length] ?? 'limited';

    return {
      id: member.id,
      name: member.name,
      email: member.email,
      allocationPercentage: member.allocationPercentage,
      pendingExpenses: member.pendingExpenses,
      role,
      roleLabel:
        role === 'admin'
          ? 'Administrador'
          : role === 'reconciliation'
            ? 'Conciliación'
            : 'Aprobador',
      access,
      accessLabel:
        access === 'full'
          ? 'Acceso total'
          : access === 'read-move'
            ? 'Lectura y movimientos'
            : 'Permisos limitados',
      status: index === members.length - 1 ? ('invited' as UserStatus) : ('active' as UserStatus),
    };
  });
});

const filteredUsers = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return accountUsers.value.filter((user) => {
    const matchesQuery =
      normalizedQuery.length === 0 ||
      user.name.toLowerCase().includes(normalizedQuery) ||
      user.email.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (selectedRoles.value.length > 0 && !selectedRoles.value.includes(user.role)) {
      return false;
    }

    if (selectedAccesses.value.length > 0 && !selectedAccesses.value.includes(user.access)) {
      return false;
    }

    return true;
  });
});

const selectedUser = computed(() => {
  if (!selectedUserId.value) {
    return null;
  }

  return accountUsers.value.find((user) => user.id === selectedUserId.value) ?? null;
});

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedRoles.value = [];
  selectedAccesses.value = [];
}

function toggleRole(role: UserRole): void {
  if (selectedRoles.value.includes(role)) {
    selectedRoles.value = selectedRoles.value.filter((item) => item !== role);
    return;
  }

  selectedRoles.value = [...selectedRoles.value, role];
}

function toggleAccess(access: UserAccess): void {
  if (selectedAccesses.value.includes(access)) {
    selectedAccesses.value = selectedAccesses.value.filter((item) => item !== access);
    return;
  }

  selectedAccesses.value = [...selectedAccesses.value, access];
}

function handleFiltersModalAction(actionKey: string): void {
  if (actionKey === 'clear') {
    clearFilters();
    return;
  }

  if (actionKey === 'close') {
    closeFilters();
  }
}

function openCreateUser(): void {
  isCreateUserOpen.value = true;
}

function closeCreateUser(): void {
  isCreateUserOpen.value = false;
}

function openEditUser(userId: string): void {
  selectedUserId.value = userId;
  isEditUserOpen.value = true;
}

function closeEditUser(): void {
  isEditUserOpen.value = false;
  selectedUserId.value = null;
}

function openDeleteUser(userId: string): void {
  selectedUserId.value = userId;
  isDeleteUserOpen.value = true;
}

function closeDeleteUser(): void {
  isDeleteUserOpen.value = false;
  selectedUserId.value = null;
}

function confirmDeleteUser(): void {
  closeDeleteUser();
}
</script>

<template>
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Usuarios</AppTitle>
          <AppText>Gestión embebida de miembros, roles y permisos de la cuenta.</AppText>
        </div>

        <AppButton variant="primary" @click="openCreateUser">
          <PlusIcon class="h-4 w-4" />
        </AppButton>
      </div>
    </AppCard>

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[var(--app-color-text-subtle)]"
        >
          <MagnifyingGlassIcon class="h-5 w-5" />
        </div>
        <AppInput
          id="user-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar usuario por nombre"
          class="pl-11"
        />
      </div>

      <AppIconButton ariaLabel="Abrir filtros avanzados" @click="openFilters">
        <AdjustmentsHorizontalIcon class="h-5 w-5" />
      </AppIconButton>
    </div>

    <section class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">{{ filteredUsers.length }} usuarios visibles</AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <AccountUserListItem
          v-for="user in filteredUsers"
          :key="user.id"
          :access-label="user.accessLabel"
          :allocation-percentage="user.allocationPercentage"
          :email="user.email"
          :item-id="user.id"
          :name="user.name"
          :pending-expenses="user.pendingExpenses"
          :role-label="user.roleLabel"
          :status="user.status"
          @delete="openDeleteUser"
          @edit="openEditUser"
        />

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para revisar más miembros conforme crezca la colaboración de la cuenta.
          </AppText>
        </div>
      </div>
    </section>

    <AppModal
      :open="isFiltersOpen"
      :actions="[
        { key: 'clear', label: 'Limpiar filtros', tone: 'neutral', icon: ArrowPathIcon },
        { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
      ]"
      title="Filtros avanzados"
      variant="default"
      @action="handleFiltersModalAction"
      @close="closeFilters"
    >
      <div class="space-y-5">
        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Rol</AppTitle>
          <AppText>Refina la lista según el tipo de responsabilidad dentro de la cuenta.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="role in userRoleOptions"
            :key="role.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              selectedRoles.includes(role.value)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)]'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleRole(role.value)"
          >
            {{ role.label }}
          </button>
        </div>

        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Acceso</AppTitle>
          <AppText>Filtra entre niveles de permiso y operación disponibles para cada usuario.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="access in userAccessOptions"
            :key="access.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              selectedAccesses.includes(access.value)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)]'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleAccess(access.value)"
          >
            {{ access.label }}
          </button>
        </div>
      </div>
    </AppModal>

    <AppModal
      :open="isCreateUserOpen"
      :actions="[{ key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true }]"
      title="Agregar usuario"
      variant="default"
      @close="closeCreateUser"
    >
      <div class="space-y-3">
        <AppText>
          La invitación o asignación de usuarios puede integrarse aquí siguiendo la misma estructura modal.
        </AppText>
        <AppText size="sm" tone="subtle">
          Por ahora dejamos listo el flujo visual y el listado administrable con búsqueda y filtros.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isEditUserOpen"
      :actions="[{ key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true }]"
      title="Editar usuario"
      variant="default"
      @close="closeEditUser"
    >
      <div class="space-y-3">
        <AppText>
          El formulario de edición para
          <strong>{{ selectedUser?.name }}</strong>
          se mostrará aquí eventualmente.
        </AppText>
        <AppText size="sm" tone="subtle">
          Dejamos listo el punto de integración para mantener el mismo patrón modal de edición.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isDeleteUserOpen"
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        { key: 'confirm-delete-user', label: 'Eliminar usuario', tone: 'primary' },
      ]"
      title="Eliminar usuario"
      variant="danger"
      @action="($event === 'confirm-delete-user') && confirmDeleteUser()"
      @close="closeDeleteUser"
    >
      <div class="space-y-3">
        <AppText>
          Vas a eliminar a
          <strong>{{ selectedUser?.name }}</strong>
          de esta cuenta.
        </AppText>
        <AppText size="sm" tone="subtle">
          La confirmación sigue el mismo patrón del resto de módulos con acciones contextuales.
        </AppText>
      </div>
    </AppModal>
  </section>
</template>
