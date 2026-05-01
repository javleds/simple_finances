<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  CheckBadgeIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  SparklesIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

import SubscriptionListItem from '@/modules/subscriptions/components/SubscriptionListItem.vue';
import {
  AppButton,
  AppCard,
  AppIconButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

const subscriptions = [
  {
    id: 'premium-facility',
    plan: 'Plan Premium Facility',
    cycle: 'Facturación anual',
    nextCharge: '12 de mayo de 2026',
    amount: 12000,
    status: 'active',
  },
  {
    id: 'additional-users',
    plan: 'Usuarios adicionales',
    cycle: 'Facturación mensual',
    nextCharge: '03 de mayo de 2026',
    amount: 1280,
    status: 'pending-renewal',
  },
  {
    id: 'advanced-analytics',
    plan: 'Analítica avanzada',
    cycle: 'Complemento mensual',
    nextCharge: '18 de mayo de 2026',
    amount: 860,
    status: 'paused',
  },
] as const;

type SubscriptionStatus = 'active' | 'pending-renewal' | 'paused';
type SubscriptionCycle = 'annual' | 'monthly' | 'add-on';

const searchTerm = ref('');
const isFiltersOpen = ref(false);
const isCreateSubscriptionOpen = ref(false);
const isEditSubscriptionOpen = ref(false);
const isDeleteSubscriptionOpen = ref(false);
const selectedStatuses = ref<SubscriptionStatus[]>([]);
const selectedCycles = ref<SubscriptionCycle[]>([]);
const selectedSubscriptionId = ref<string | null>(null);

const subscriptionStatusOptions = [
  { value: 'active', label: 'Activa' },
  { value: 'pending-renewal', label: 'Por renovar' },
  { value: 'paused', label: 'Pausada' },
] as const;

const subscriptionCycleOptions = [
  { value: 'annual', label: 'Anual' },
  { value: 'monthly', label: 'Mensual' },
  { value: 'add-on', label: 'Complemento' },
] as const;

const normalizedSubscriptions = computed(() =>
  subscriptions.map((subscription) => ({
    ...subscription,
    cycleType:
      subscription.id === 'premium-facility'
        ? ('annual' as SubscriptionCycle)
        : subscription.id === 'additional-users'
          ? ('monthly' as SubscriptionCycle)
          : ('add-on' as SubscriptionCycle),
  })),
);

const filteredSubscriptions = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return normalizedSubscriptions.value.filter((subscription) => {
    const matchesQuery =
      normalizedQuery.length === 0 || subscription.plan.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (
      selectedStatuses.value.length > 0 &&
      !selectedStatuses.value.includes(subscription.status)
    ) {
      return false;
    }

    if (selectedCycles.value.length > 0 && !selectedCycles.value.includes(subscription.cycleType)) {
      return false;
    }

    return true;
  });
});

const selectedSubscription = computed(() => {
  if (!selectedSubscriptionId.value) {
    return null;
  }

  return (
    normalizedSubscriptions.value.find(
      (subscription) => subscription.id === selectedSubscriptionId.value,
    ) ?? null
  );
});

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
  selectedCycles.value = [];
}

function toggleStatus(status: SubscriptionStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function toggleCycle(cycle: SubscriptionCycle): void {
  if (selectedCycles.value.includes(cycle)) {
    selectedCycles.value = selectedCycles.value.filter((item) => item !== cycle);
    return;
  }

  selectedCycles.value = [...selectedCycles.value, cycle];
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

function openCreateSubscription(): void {
  isCreateSubscriptionOpen.value = true;
}

function closeCreateSubscription(): void {
  isCreateSubscriptionOpen.value = false;
}

function openEditSubscription(subscriptionId: string): void {
  selectedSubscriptionId.value = subscriptionId;
  isEditSubscriptionOpen.value = true;
}

function closeEditSubscription(): void {
  isEditSubscriptionOpen.value = false;
  selectedSubscriptionId.value = null;
}

function openDeleteSubscription(subscriptionId: string): void {
  selectedSubscriptionId.value = subscriptionId;
  isDeleteSubscriptionOpen.value = true;
}

function closeDeleteSubscription(): void {
  isDeleteSubscriptionOpen.value = false;
  selectedSubscriptionId.value = null;
}

function confirmDeleteSubscription(): void {
  closeDeleteSubscription();
}
</script>

<template>
  <div class="space-y-5">
    <AppCard class="overflow-hidden !p-0">
      <div class="relative px-5 py-6 sm:px-6">
        <div
          class="absolute inset-x-0 top-0 h-24 bg-[linear-gradient(135deg,#0f766e,color-mix(in_srgb,#0f766e_52%,white))] opacity-95"
        />

        <div class="relative space-y-4">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <AppText size="sm" tone="subtle" class="!text-white/80">
                Cobertura contratada
              </AppText>
              <AppTitle as="h2" size="sm" class="!text-white"> Subscripciones activas </AppTitle>
            </div>

            <div
              class="rounded-2xl border border-white/20 bg-white/10 px-3 py-2 text-right backdrop-blur"
            >
              <p class="text-xs font-medium text-white/75">Estado</p>
              <p class="text-sm font-semibold text-white">Al día</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="rounded-3xl border border-white/15 bg-white/12 p-4">
              <SparklesIcon class="h-5 w-5 text-white" />
              <p class="mt-3 text-sm text-white/75">Módulos activos</p>
              <p class="mt-1 text-2xl font-semibold text-white">7</p>
            </div>
            <div class="rounded-3xl border border-white/15 bg-white/12 p-4">
              <CheckBadgeIcon class="h-5 w-5 text-white" />
              <p class="mt-3 text-sm text-white/75">Renovación</p>
              <p class="mt-1 text-2xl font-semibold text-white">Auto</p>
            </div>
          </div>
        </div>
      </div>
    </AppCard>

    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Planes y cargos</AppTitle>
          <AppText>Detalle de las suscripciones vinculadas a esta organización.</AppText>
        </div>

        <AppButton variant="primary" @click="openCreateSubscription">
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
          id="subscription-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar suscripción por nombre"
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
          {{ filteredSubscriptions.length }} suscripciones visibles
        </AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <SubscriptionListItem
          v-for="subscription in filteredSubscriptions"
          :key="subscription.id"
          :amount="subscription.amount"
          :cycle="subscription.cycle"
          :item-id="subscription.id"
          :next-charge="subscription.nextCharge"
          :plan="subscription.plan"
          :status="subscription.status"
          @delete="openDeleteSubscription"
          @edit="openEditSubscription"
        />

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para revisar más planes y complementos conforme crezca la cobertura
            contratada.
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
          <AppText>Filtra la cobertura según el estado operativo de cada suscripción.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in subscriptionStatusOptions"
            :key="status.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-[var(--app-color-focus-ring)] focus:outline-none"
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
          <AppTitle as="h2" size="sm">Ciclo</AppTitle>
          <AppText>Refina entre planes anuales, mensuales o complementos especializados.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="cycle in subscriptionCycleOptions"
            :key="cycle.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-[var(--app-color-focus-ring)] focus:outline-none"
            :class="
              selectedCycles.includes(cycle.value)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)]'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleCycle(cycle.value)"
          >
            {{ cycle.label }}
          </button>
        </div>
      </div>
    </AppModal>

    <AppModal
      :open="isCreateSubscriptionOpen"
      :actions="[
        { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
      ]"
      title="Nueva suscripción"
      variant="default"
      @close="closeCreateSubscription"
    >
      <div class="space-y-3">
        <AppText>
          La creación o ampliación de subscripciones puede integrarse aquí con el mismo patrón modal
          del resto de la app.
        </AppText>
        <AppText size="sm" tone="subtle">
          Por ahora dejamos preparado el flujo visual de búsqueda, filtros y acciones contextuales.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isEditSubscriptionOpen"
      :actions="[
        { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
      ]"
      title="Editar suscripción"
      variant="default"
      @close="closeEditSubscription"
    >
      <div class="space-y-3">
        <AppText>
          El formulario de edición para
          <strong>{{ selectedSubscription?.plan }}</strong>
          se mostrará aquí eventualmente.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isDeleteSubscriptionOpen"
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        { key: 'confirm-delete-subscription', label: 'Eliminar suscripción', tone: 'primary' },
      ]"
      title="Eliminar suscripción"
      variant="danger"
      @action="$event === 'confirm-delete-subscription' && confirmDeleteSubscription()"
      @close="closeDeleteSubscription"
    >
      <div class="space-y-3">
        <AppText>
          Vas a eliminar
          <strong>{{ selectedSubscription?.plan }}</strong
          >.
        </AppText>
      </div>
    </AppModal>
  </div>
</template>
