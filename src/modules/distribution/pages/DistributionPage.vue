<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  FunnelIcon,
  MagnifyingGlassIcon,
  PlayCircleIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

import DistributionRuleListItem from '@/modules/distribution/components/DistributionRuleListItem.vue';
import {
  AppButton,
  AppCard,
  AppIconButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const channels = [
  {
    id: 'daily-operations',
    name: 'Operación diaria',
    description: 'Fondeo de la cuenta principal para ejecución inmediata.',
    allocation: 42,
    destination: 'Cuenta concentradora',
    status: 'active',
  },
  {
    id: 'tax-reserve',
    name: 'Reserva fiscal',
    description: 'Separación preventiva para obligaciones del siguiente corte.',
    allocation: 25,
    destination: 'Reserva tributaria',
    status: 'active',
  },
  {
    id: 'payroll',
    name: 'Nómina',
    description: 'Distribución automática para pagos recurrentes.',
    allocation: 18,
    destination: 'Pagos y nómina',
    status: 'draft',
  },
  {
    id: 'regional-buffer',
    name: 'Bolsa regional de contingencia',
    description: 'Asignación temporal para imprevistos de operación distribuida.',
    allocation: 15,
    destination: 'Operación regional',
    status: 'paused',
  },
] as const;

type DistributionStatus = 'active' | 'draft' | 'paused';
type DistributionDestination = 'core' | 'tax' | 'payroll' | 'regional';

const searchTerm = ref('');
const isFiltersOpen = ref(false);
const isCreateRuleOpen = ref(false);
const isEditRuleOpen = ref(false);
const isDeleteRuleOpen = ref(false);
const selectedStatuses = ref<DistributionStatus[]>([]);
const selectedDestinations = ref<DistributionDestination[]>([]);
const selectedRuleId = ref<string | null>(null);

const distributionStatusOptions = [
  { value: 'active', label: 'Activa' },
  { value: 'draft', label: 'Borrador' },
  { value: 'paused', label: 'Pausada' },
] as const;

const distributionDestinationOptions = [
  { value: 'core', label: 'Concentradora' },
  { value: 'tax', label: 'Fiscal' },
  { value: 'payroll', label: 'Nómina' },
  { value: 'regional', label: 'Regional' },
] as const;

const normalizedChannels = computed(() =>
  channels.map((channel) => ({
    ...channel,
    destinationType:
      channel.id === 'daily-operations'
        ? ('core' as DistributionDestination)
        : channel.id === 'tax-reserve'
          ? ('tax' as DistributionDestination)
          : channel.id === 'payroll'
            ? ('payroll' as DistributionDestination)
            : ('regional' as DistributionDestination),
  })),
);

const filteredChannels = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return normalizedChannels.value.filter((channel) => {
    const matchesQuery =
      normalizedQuery.length === 0 || channel.name.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (selectedStatuses.value.length > 0 && !selectedStatuses.value.includes(channel.status)) {
      return false;
    }

    if (
      selectedDestinations.value.length > 0 &&
      !selectedDestinations.value.includes(channel.destinationType)
    ) {
      return false;
    }

    return true;
  });
});

const selectedChannel = computed(() => {
  if (!selectedRuleId.value) {
    return null;
  }

  return normalizedChannels.value.find((channel) => channel.id === selectedRuleId.value) ?? null;
});

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
  selectedDestinations.value = [];
}

function toggleStatus(status: DistributionStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function toggleDestination(destination: DistributionDestination): void {
  if (selectedDestinations.value.includes(destination)) {
    selectedDestinations.value = selectedDestinations.value.filter((item) => item !== destination);
    return;
  }

  selectedDestinations.value = [...selectedDestinations.value, destination];
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

function openCreateRule(): void {
  isCreateRuleOpen.value = true;
}

function closeCreateRule(): void {
  isCreateRuleOpen.value = false;
}

function openEditRule(ruleId: string): void {
  selectedRuleId.value = ruleId;
  isEditRuleOpen.value = true;
}

function closeEditRule(): void {
  isEditRuleOpen.value = false;
  selectedRuleId.value = null;
}

function openDeleteRule(ruleId: string): void {
  selectedRuleId.value = ruleId;
  isDeleteRuleOpen.value = true;
}

function closeDeleteRule(): void {
  isDeleteRuleOpen.value = false;
  selectedRuleId.value = null;
}

function confirmDeleteRule(): void {
  closeDeleteRule();
}
</script>

<template>
  <div class="space-y-5">
    <AppCard class="rounded-3xl">
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
          <AppText size="sm" tone="subtle">Motor de reglas</AppText>
          <AppTitle as="h2" size="sm">Distribución automática</AppTitle>
          <AppText> Define cómo se reparte el flujo disponible entre las cuentas destino. </AppText>
        </div>

        <div
          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[color-mix(in_srgb,var(--app-color-primary)_12%,transparent)] text-(--app-color-primary)"
        >
          <FunnelIcon class="h-6 w-6" />
        </div>
      </div>

      <div class="mt-5 flex items-center gap-3">
        <AppButton variant="primary">
          <PlayCircleIcon class="mr-2 h-4 w-4" />
          Ejecutar ahora
        </AppButton>
        <AppButton variant="outline">Editar reglas</AppButton>
      </div>
    </AppCard>

    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Canales activos</AppTitle>
          <AppText>Cada regla determina prioridad, porcentaje y cuenta destino.</AppText>
        </div>

        <AppButton variant="primary" @click="openCreateRule">
          <PlusIcon class="h-4 w-4" />
        </AppButton>
      </div>
    </AppCard>

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
        >
          <MagnifyingGlassIcon class="h-5 w-5" />
        </div>
        <AppInput
          id="distribution-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar regla por nombre"
          class="pl-11"
        />
      </div>

      <AppIconButton ariaLabel="Abrir filtros avanzados" @click="openFilters">
        <AdjustmentsHorizontalIcon class="h-5 w-5" />
      </AppIconButton>
    </div>

    <section class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle"> {{ filteredChannels.length }} reglas visibles </AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <DistributionRuleListItem
          v-for="channel in filteredChannels"
          :key="channel.id"
          :allocation="channel.allocation"
          :description="channel.description"
          :destination="channel.destination"
          :item-id="channel.id"
          :name="channel.name"
          :status="channel.status"
          @delete="openDeleteRule"
          @edit="openEditRule"
        />

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para revisar más reglas conforme se expanda la distribución
            salarial.
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
          <AppText
            >Filtra reglas según su estado operativo dentro del motor de distribución.</AppText
          >
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in distributionStatusOptions"
            :key="status.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              selectedStatuses.includes(status.value)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleStatus(status.value)"
          >
            {{ status.label }}
          </button>
        </div>

        <div class="space-y-2">
          <AppTitle as="h2" size="sm">Destino</AppTitle>
          <AppText>Refina la lista según la bolsa o cuenta objetivo de cada regla.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="destination in distributionDestinationOptions"
            :key="destination.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              selectedDestinations.includes(destination.value)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleDestination(destination.value)"
          >
            {{ destination.label }}
          </button>
        </div>
      </div>
    </AppModal>

    <AppModal
      :open="isCreateRuleOpen"
      :actions="[
        { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
      ]"
      title="Nueva regla"
      variant="default"
      @close="closeCreateRule"
    >
      <div class="space-y-3">
        <AppText>
          La creación de reglas de distribución puede vivir aquí con el mismo patrón modal de la
          aplicación.
        </AppText>
        <AppText size="sm" tone="subtle">
          Por ahora dejamos lista la experiencia de búsqueda, filtros, acciones y scroll continuo.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isEditRuleOpen"
      :actions="[
        { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
      ]"
      title="Editar regla"
      variant="default"
      @close="closeEditRule"
    >
      <div class="space-y-3">
        <AppText>
          El formulario de edición para
          <strong>{{ selectedChannel?.name }}</strong>
          se mostrará aquí eventualmente.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isDeleteRuleOpen"
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        { key: 'confirm-delete-rule', label: 'Eliminar regla', tone: 'primary' },
      ]"
      title="Eliminar regla"
      variant="danger"
      @action="$event === 'confirm-delete-rule' && confirmDeleteRule()"
      @close="closeDeleteRule"
    >
      <div class="space-y-3">
        <AppText>
          Vas a eliminar
          <strong>{{ selectedChannel?.name }}</strong
          >.
        </AppText>
      </div>
    </AppModal>
  </div>
</template>
