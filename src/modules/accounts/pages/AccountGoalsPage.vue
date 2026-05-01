<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

import AccountGoalListItem from '@/modules/accounts/components/AccountGoalListItem.vue';
import {
  AppButton,
  AppCard,
  AppIconButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type GoalStatus = 'on-track' | 'at-risk' | 'completed';
type GoalCadence = 'monthly' | 'quarterly' | 'strategic';

const goalItems = [
  {
    id: 'reserve-quarterly',
    title: 'Ahorro de reserva trimestral para contingencias operativas y ajustes de flujo',
    detail: 'Meta para proteger liquidez frente a cierres extraordinarios y gastos no planeados.',
    currentAmount: 68000,
    targetAmount: 100000,
    progress: 68,
    status: 'on-track',
    cadence: 'quarterly',
    cadenceLabel: 'Trimestral',
  },
  {
    id: 'monthly-operations',
    title: 'Fondo operativo mensual',
    detail: 'Objetivo para cubrir gastos recurrentes del siguiente ciclo sin tensionar la caja principal.',
    currentAmount: 24500,
    targetAmount: 30000,
    progress: 82,
    status: 'on-track',
    cadence: 'monthly',
    cadenceLabel: 'Mensual',
  },
  {
    id: 'tax-buffer',
    title: 'Colchón fiscal de cierre anual',
    detail: 'Reserva preventiva para impuestos, ajustes regulatorios y variaciones de cierre.',
    currentAmount: 41000,
    targetAmount: 90000,
    progress: 46,
    status: 'at-risk',
    cadence: 'strategic',
    cadenceLabel: 'Estratégica',
  },
  {
    id: 'regional-expansion',
    title: 'Bolsa para expansión regional',
    detail: 'Meta destinada a contratación operativa, traslados y activación de nuevas plazas.',
    currentAmount: 120000,
    targetAmount: 120000,
    progress: 100,
    status: 'completed',
    cadence: 'strategic',
    cadenceLabel: 'Estratégica',
  },
] as const;

const searchTerm = ref('');
const isFiltersOpen = ref(false);
const isCreateGoalOpen = ref(false);
const isEditGoalOpen = ref(false);
const isDeleteGoalOpen = ref(false);
const selectedStatuses = ref<GoalStatus[]>([]);
const selectedCadences = ref<GoalCadence[]>([]);
const selectedGoalId = ref<string | null>(null);

const goalStatusOptions = [
  { value: 'on-track', label: 'En curso' },
  { value: 'at-risk', label: 'En riesgo' },
  { value: 'completed', label: 'Completada' },
] as const;

const goalCadenceOptions = [
  { value: 'monthly', label: 'Mensual' },
  { value: 'quarterly', label: 'Trimestral' },
  { value: 'strategic', label: 'Estratégica' },
] as const;

const filteredGoalItems = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return goalItems.filter((goal) => {
    const matchesQuery =
      normalizedQuery.length === 0 || goal.title.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    if (selectedStatuses.value.length > 0 && !selectedStatuses.value.includes(goal.status)) {
      return false;
    }

    if (selectedCadences.value.length > 0 && !selectedCadences.value.includes(goal.cadence)) {
      return false;
    }

    return true;
  });
});

const selectedGoal = computed(() => {
  if (!selectedGoalId.value) {
    return null;
  }

  return goalItems.find((goal) => goal.id === selectedGoalId.value) ?? null;
});

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
  selectedCadences.value = [];
}

function toggleStatus(status: GoalStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function toggleCadence(cadence: GoalCadence): void {
  if (selectedCadences.value.includes(cadence)) {
    selectedCadences.value = selectedCadences.value.filter((item) => item !== cadence);
    return;
  }

  selectedCadences.value = [...selectedCadences.value, cadence];
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

function openCreateGoal(): void {
  isCreateGoalOpen.value = true;
}

function closeCreateGoal(): void {
  isCreateGoalOpen.value = false;
}

function openEditGoal(goalId: string): void {
  selectedGoalId.value = goalId;
  isEditGoalOpen.value = true;
}

function closeEditGoal(): void {
  isEditGoalOpen.value = false;
  selectedGoalId.value = null;
}

function openDeleteGoal(goalId: string): void {
  selectedGoalId.value = goalId;
  isDeleteGoalOpen.value = true;
}

function closeDeleteGoal(): void {
  isDeleteGoalOpen.value = false;
  selectedGoalId.value = null;
}

function confirmDeleteGoal(): void {
  closeDeleteGoal();
}
</script>

<template>
  <section class="space-y-4">
    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Metas financieras</AppTitle>
          <AppText>Cada meta vive dentro de la cuenta y comparte su mismo contexto.</AppText>
        </div>

        <AppButton variant="primary" @click="openCreateGoal">
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
          id="goal-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar meta por nombre"
          class="pl-11"
        />
      </div>

      <AppIconButton ariaLabel="Abrir filtros avanzados" @click="openFilters">
        <AdjustmentsHorizontalIcon class="h-5 w-5" />
      </AppIconButton>
    </div>

    <section class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle">{{ filteredGoalItems.length }} metas visibles</AppText>
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <AccountGoalListItem
          v-for="goal in filteredGoalItems"
          :key="goal.id"
          :cadence-label="goal.cadenceLabel"
          :current-amount="goal.currentAmount"
          :detail="goal.detail"
          :item-id="goal.id"
          :progress="goal.progress"
          :status="goal.status"
          :target-amount="goal.targetAmount"
          :title="goal.title"
          @delete="openDeleteGoal"
          @edit="openEditGoal"
        />

        <div
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            Sigue desplazándote para revisar más metas conforme la cuenta acumule objetivos.
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
          <AppText>Filtra metas según su nivel de avance y riesgo operativo.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in goalStatusOptions"
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
          <AppTitle as="h2" size="sm">Cadencia</AppTitle>
          <AppText>Refina la lista por temporalidad o naturaleza de cada meta.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="cadence in goalCadenceOptions"
            :key="cadence.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :class="
              selectedCadences.includes(cadence.value)
                ? 'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)]'
                : 'bg-[var(--app-color-surface-muted)] text-[var(--app-color-text)]'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleCadence(cadence.value)"
          >
            {{ cadence.label }}
          </button>
        </div>
      </div>
    </AppModal>

    <AppModal
      :open="isCreateGoalOpen"
      :actions="[{ key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true }]"
      title="Crear meta"
      variant="default"
      @close="closeCreateGoal"
    >
      <div class="space-y-3">
        <AppText>
          La creación de metas puede vivir en esta modal siguiendo el mismo patrón del resto del
          módulo.
        </AppText>
        <AppText size="sm" tone="subtle">
          Por ahora dejamos el flujo visual preparado mientras se define el formulario específico.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isEditGoalOpen"
      :actions="[{ key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true }]"
      title="Editar meta"
      variant="default"
      @close="closeEditGoal"
    >
      <div class="space-y-3">
        <AppText>
          El formulario de edición para
          <strong>{{ selectedGoal?.title }}</strong>
          se mostrará aquí en una siguiente iteración.
        </AppText>
        <AppText size="sm" tone="subtle">
          El flujo modal ya quedó reservado para mantener consistencia con el resto del sistema.
        </AppText>
      </div>
    </AppModal>

    <AppModal
      :open="isDeleteGoalOpen"
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        { key: 'confirm-delete-goal', label: 'Eliminar meta', tone: 'primary' },
      ]"
      title="Eliminar meta"
      variant="danger"
      @action="($event === 'confirm-delete-goal') && confirmDeleteGoal()"
      @close="closeDeleteGoal"
    >
      <div class="space-y-3">
        <AppText>
          Vas a eliminar
          <strong>{{ selectedGoal?.title }}</strong>.
        </AppText>
        <AppText size="sm" tone="subtle">
          La confirmación sigue el mismo patrón de borrado del resto de las facilities con listados.
        </AppText>
      </div>
    </AppModal>
  </section>
</template>
