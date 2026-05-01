<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

import FacilityInvitationListItem from '@/modules/admin/components/FacilityInvitationListItem.vue';
import {
  AppIconButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type InvitationStatus = 'pending' | 'expiring';
type InvitationSource = 'finance' | 'operations' | 'leadership';

const invitationItems = [
  {
    id: 'reserve-tributaria',
    accountName: 'Reserva tributaria',
    invitedBy: 'Sofía Mora',
    status: 'pending',
    source: 'finance',
    metaLabel: 'Invitación recibida hoy',
  },
  {
    id: 'operacion-regional',
    accountName: 'Operación regional',
    invitedBy: 'Josefina Ramos',
    status: 'expiring',
    source: 'operations',
    metaLabel: 'Expira en 24 horas',
  },
  {
    id: 'inversiones-liquidas',
    accountName: 'Inversiones líquidas',
    invitedBy: 'Valeria Muñoz',
    status: 'pending',
    source: 'leadership',
    metaLabel: 'Invitación pendiente',
  },
] as const;

const searchTerm = ref('');
const isFiltersOpen = ref(false);
const selectedStatuses = ref<InvitationStatus[]>([]);
const selectedSources = ref<InvitationSource[]>([]);

const invitationStatusOptions = [
  { value: 'pending', label: 'Pendiente' },
  { value: 'expiring', label: 'Expira pronto' },
] as const;

const invitationSourceOptions = [
  { value: 'finance', label: 'Finanzas' },
  { value: 'operations', label: 'Operación' },
  { value: 'leadership', label: 'Liderazgo' },
] as const;

const filteredInvitationItems = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return invitationItems.filter((invitation) => {
    const matchesQuery =
      normalizedQuery.length === 0 ||
      invitation.accountName.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (selectedStatuses.value.length > 0 && !selectedStatuses.value.includes(invitation.status)) {
      return false;
    }

    if (selectedSources.value.length > 0 && !selectedSources.value.includes(invitation.source)) {
      return false;
    }

    return true;
  });
});

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
  selectedSources.value = [];
}

function toggleStatus(status: InvitationStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function toggleSource(source: InvitationSource): void {
  if (selectedSources.value.includes(source)) {
    selectedSources.value = selectedSources.value.filter((item) => item !== source);
    return;
  }

  selectedSources.value = [...selectedSources.value, source];
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

function handleAcceptInvitation(): void {}

function handleRejectInvitation(): void {}
</script>

<template>
  <section class="space-y-4">
    <div class="space-y-1">
      <AppTitle as="h2" size="sm">Invitaciones</AppTitle>
      <AppText>Revisa las cuentas a las que aún no te has unido dentro de esta facility.</AppText>
    </div>

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[var(--app-color-text-subtle)]"
        >
          <MagnifyingGlassIcon class="h-5 w-5" />
        </div>
        <AppInput
          id="facility-invitation-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar invitación por cuenta"
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
        <FacilityInvitationListItem
          v-for="invitation in filteredInvitationItems"
          :key="invitation.id"
          :account-name="invitation.accountName"
          :invited-by="invitation.invitedBy"
          :item-id="invitation.id"
          :meta-label="invitation.metaLabel"
          :status="invitation.status"
          @accept="handleAcceptInvitation"
          @reject="handleRejectInvitation"
        />

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para revisar más invitaciones conforme crezca la colaboración entre cuentas.
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
          <AppText>Filtra invitaciones según su urgencia o su estado de respuesta.</AppText>
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
          <AppTitle as="h2" size="sm">Origen</AppTitle>
          <AppText>Refina según el área o perfil que emitió la invitación a la cuenta.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="source in invitationSourceOptions"
            :key="source.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              selectedSources.includes(source.value)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)]'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleSource(source.value)"
          >
            {{ source.label }}
          </button>
        </div>
      </div>
    </AppModal>
  </section>
</template>
