<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

import AccountInvitationListItem from '@/modules/accounts/components/AccountInvitationListItem.vue';
import {
  AppButton,
  AppCard,
  AppIconButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type InvitationStatus = 'pending' | 'resent' | 'accepted';
type InvitationRole = 'admin' | 'approver' | 'finance';

const invitationItems = [
  {
    id: 'carlos-mendoza',
    name: 'Carlos Mendoza',
    email: 'carlos@empresa.com',
    role: 'approver',
    roleLabel: 'Aprobador',
    metaLabel: 'Expira en 3 días',
    status: 'pending',
  },
  {
    id: 'maria-torres',
    name: 'María Torres',
    email: 'maria@empresa.com',
    role: 'finance',
    roleLabel: 'Seguimiento financiero',
    metaLabel: 'Reenviada ayer',
    status: 'resent',
  },
  {
    id: 'paola-garcia',
    name: 'Paola García',
    email: 'paola@empresa.com',
    role: 'admin',
    roleLabel: 'Administrador',
    metaLabel: 'Aceptada hace 2 días',
    status: 'accepted',
  },
] as const;

const searchTerm = ref('');
const isFiltersOpen = ref(false);
const isCreateInvitationOpen = ref(false);
const isEditInvitationOpen = ref(false);
const isDeleteInvitationOpen = ref(false);
const selectedStatuses = ref<InvitationStatus[]>([]);
const selectedRoles = ref<InvitationRole[]>([]);
const selectedInvitationId = ref<string | null>(null);

const invitationStatusOptions = [
  { value: 'pending', label: 'Pendiente' },
  { value: 'resent', label: 'Reenviada' },
  { value: 'accepted', label: 'Aceptada' },
] as const;

const invitationRoleOptions = [
  { value: 'admin', label: 'Administrador' },
  { value: 'approver', label: 'Aprobador' },
  { value: 'finance', label: 'Finanzas' },
] as const;

const filteredInvitationItems = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return invitationItems.filter((invitation) => {
    const matchesQuery =
      normalizedQuery.length === 0 ||
      invitation.name.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (selectedStatuses.value.length > 0 && !selectedStatuses.value.includes(invitation.status)) {
      return false;
    }

    if (selectedRoles.value.length > 0 && !selectedRoles.value.includes(invitation.role)) {
      return false;
    }

    return true;
  });
});

const selectedInvitation = computed(() => {
  if (!selectedInvitationId.value) {
    return null;
  }

  return invitationItems.find((invitation) => invitation.id === selectedInvitationId.value) ?? null;
});

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
  selectedRoles.value = [];
}

function toggleStatus(status: InvitationStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function toggleRole(role: InvitationRole): void {
  if (selectedRoles.value.includes(role)) {
    selectedRoles.value = selectedRoles.value.filter((item) => item !== role);
    return;
  }

  selectedRoles.value = [...selectedRoles.value, role];
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

function openCreateInvitation(): void {
  isCreateInvitationOpen.value = true;
}

function closeCreateInvitation(): void {
  isCreateInvitationOpen.value = false;
}

function openEditInvitation(invitationId: string): void {
  selectedInvitationId.value = invitationId;
  isEditInvitationOpen.value = true;
}

function closeEditInvitation(): void {
  isEditInvitationOpen.value = false;
  selectedInvitationId.value = null;
}

function openDeleteInvitation(invitationId: string): void {
  selectedInvitationId.value = invitationId;
  isDeleteInvitationOpen.value = true;
}

function closeDeleteInvitation(): void {
  isDeleteInvitationOpen.value = false;
  selectedInvitationId.value = null;
}

function confirmDeleteInvitation(): void {
  closeDeleteInvitation();
}
</script>

<template>
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Invitaciones</AppTitle>
          <AppText>Invita usuarios a colaborar dentro de esta cuenta.</AppText>
        </div>
        <AppButton variant="primary" @click="openCreateInvitation">
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
          id="invitation-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar invitación por nombre"
          class="pl-11"
        />
      </div>

      <AppIconButton ariaLabel="Abrir filtros avanzados" @click="openFilters">
        <AdjustmentsHorizontalIcon class="h-5 w-5" />
      </AppIconButton>
    </div>

    <section class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">
          {{ filteredInvitationItems.length }} invitaciones visibles
        </AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <AccountInvitationListItem
          v-for="invitation in filteredInvitationItems"
          :key="invitation.id"
          :email="invitation.email"
          :item-id="invitation.id"
          :meta-label="invitation.metaLabel"
          :name="invitation.name"
          :role-label="invitation.roleLabel"
          :status="invitation.status"
          @delete="openDeleteInvitation"
          @edit="openEditInvitation"
        />

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para revisar más invitaciones conforme se amplíe la colaboración.
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
          <AppTitle as="h2" size="sm">Estatus</AppTitle>
          <AppText>Refina las invitaciones según su momento dentro del flujo de acceso.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in invitationStatusOptions"
            :key="status.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              selectedStatuses.includes(status.value)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)]'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleStatus(status.value)"
          >
            {{ status.label }}
          </button>
        </div>

        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Rol</AppTitle>
          <AppText>Filtra por el tipo de invitación que se está enviando a cada colaborador.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="role in invitationRoleOptions"
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
      </div>
    </AppModal>

    <AppModal
      :open="isCreateInvitationOpen"
      :actions="[{ key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true }]"
      title="Nueva invitación"
      variant="default"
      @close="closeCreateInvitation"
    >
      <div class="space-y-3">
        <AppText>
          La creación de invitaciones puede integrarse aquí siguiendo el mismo patrón modal del resto del módulo.
        </AppText>
        <AppText size="sm" tone="subtle">
          Por ahora dejamos preparado el flujo visual con búsqueda, filtros y acciones de lista.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isEditInvitationOpen"
      :actions="[{ key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true }]"
      title="Editar invitación"
      variant="default"
      @close="closeEditInvitation"
    >
      <div class="space-y-3">
        <AppText>
          El formulario de edición para la invitación de
          <strong>{{ selectedInvitation?.name }}</strong>
          se mostrará aquí eventualmente.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isDeleteInvitationOpen"
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        { key: 'confirm-delete-invitation', label: 'Eliminar invitación', tone: 'primary' },
      ]"
      title="Eliminar invitación"
      variant="danger"
      @action="($event === 'confirm-delete-invitation') && confirmDeleteInvitation()"
      @close="closeDeleteInvitation"
    >
      <div class="space-y-3">
        <AppText>
          Vas a eliminar la invitación de
          <strong>{{ selectedInvitation?.name }}</strong>.
        </AppText>
      </div>
    </AppModal>
  </section>
</template>
