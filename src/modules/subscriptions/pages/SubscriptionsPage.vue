<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, onMounted, ref } from 'vue';

import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';
import SubscriptionsForm from '@/modules/subscriptions/components/SubscriptionsForm.vue';
import SubscriptionListItem from '@/modules/subscriptions/components/SubscriptionListItem.vue';
import { useSubscriptionsCrud } from '@/modules/subscriptions/composables/useSubscriptionsCrud';
import { formatSubscriptionFrequency } from '@/modules/subscriptions/schemas/subscriptionSchemas';
import type {
  SubscriptionFrequencyType,
  SubscriptionWritePayload,
} from '@/modules/subscriptions/types';
import {
  AppButton,
  AppCard,
  AppIconButton,
  AppInput,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type SubscriptionStatusFilter = 'active' | 'cancelled';

const accountsRepository = createAccountsRepository();

const searchTerm = ref('');
const isFiltersOpen = ref(false);
const isCreateSubscriptionOpen = ref(false);
const isEditSubscriptionOpen = ref(false);
const isDeleteSubscriptionOpen = ref(false);
const selectedStatuses = ref<SubscriptionStatusFilter[]>([]);
const selectedUnits = ref<SubscriptionFrequencyType[]>([]);
const selectedSubscriptionId = ref<string | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const fundingAccountOptions = ref<Array<{ value: string; label: string; description?: string }>>([]);

const subscriptionStatusOptions = [
  { value: 'active', label: 'Activa' },
  { value: 'cancelled', label: 'Cancelada' },
] as const;

const subscriptionUnitOptions = [
  { value: 'days', label: 'Días' },
  { value: 'months', label: 'Meses' },
  { value: 'years', label: 'Años' },
] as const;

const {
  subscriptions,
  hasSubscriptions,
  isLoading,
  isSaving,
  isDeleting,
  loadError,
  saveError,
  deleteError,
  clearSaveError,
  clearDeleteError,
  loadSubscriptions,
  createSubscription,
  updateSubscription,
  deleteSubscription,
} = useSubscriptionsCrud();

const filteredSubscriptions = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  return subscriptions.value.filter((subscription) => {
    const matchesQuery =
      normalizedQuery.length === 0 || subscription.name.toLowerCase().includes(normalizedQuery);

    if (!matchesQuery) {
      return false;
    }

    const status: SubscriptionStatusFilter = subscription.finishedAt ? 'cancelled' : 'active';

    if (selectedStatuses.value.length > 0 && !selectedStatuses.value.includes(status)) {
      return false;
    }

    if (
      selectedUnits.value.length > 0 &&
      !selectedUnits.value.includes(subscription.frequencyType)
    ) {
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
    subscriptions.value.find(
      (subscription) => subscription.id === selectedSubscriptionId.value,
    ) ?? null
  );
});

const createSubscriptionActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-subscription',
    label: isSaving.value ? 'Guardando...' : 'Crear suscripción',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'subscription-form',
    disabled: !createFormState.value.canSubmit || isSaving.value,
  },
]);

const editSubscriptionActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-edit-subscription',
    label: isSaving.value ? 'Guardando...' : 'Guardar cambios',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'edit-subscription-form',
    disabled: !editFormState.value.canSubmit || isSaving.value,
  },
]);

const deleteSubscriptionActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-delete-subscription',
    label: isDeleting.value ? 'Eliminando...' : 'Eliminar suscripción',
    tone: 'primary' as const,
    disabled: !selectedSubscription.value || isDeleting.value,
  },
]);

onMounted(() => {
  void Promise.all([loadSubscriptions(), loadFundingAccounts()]);
});

async function loadFundingAccounts(): Promise<void> {
  try {
    const accounts = await accountsRepository.list();
    fundingAccountOptions.value = accounts.map((account) => ({
      value: account.id,
      label: account.name,
      description: account.description,
    }));
  } catch {
    fundingAccountOptions.value = [];
  }
}

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
  selectedUnits.value = [];
}

function toggleStatus(status: SubscriptionStatusFilter): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
}

function toggleUnit(unit: SubscriptionFrequencyType): void {
  if (selectedUnits.value.includes(unit)) {
    selectedUnits.value = selectedUnits.value.filter((item) => item !== unit);
    return;
  }

  selectedUnits.value = [...selectedUnits.value, unit];
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
  clearSaveError();
  createFormState.value = { canSubmit: false, isSubmitting: false };
  isCreateSubscriptionOpen.value = true;
}

function closeCreateSubscription(): void {
  isCreateSubscriptionOpen.value = false;
  clearSaveError();
}

function openEditSubscription(subscriptionId: string): void {
  clearSaveError();
  selectedSubscriptionId.value = subscriptionId;
  editFormState.value = { canSubmit: false, isSubmitting: false };
  isEditSubscriptionOpen.value = true;
}

function closeEditSubscription(): void {
  isEditSubscriptionOpen.value = false;
  selectedSubscriptionId.value = null;
  clearSaveError();
}

function openDeleteSubscription(subscriptionId: string): void {
  clearDeleteError();
  selectedSubscriptionId.value = subscriptionId;
  isDeleteSubscriptionOpen.value = true;
}

function closeDeleteSubscription(): void {
  isDeleteSubscriptionOpen.value = false;
  selectedSubscriptionId.value = null;
  clearDeleteError();
}

async function handleCreateSubscriptionSubmit(payload: SubscriptionWritePayload): Promise<void> {
  const wasCreated = await createSubscription(payload);

  if (wasCreated) {
    closeCreateSubscription();
  }
}

async function handleEditSubscriptionSubmit(payload: SubscriptionWritePayload): Promise<void> {
  if (!selectedSubscription.value) {
    return;
  }

  const wasUpdated = await updateSubscription(selectedSubscription.value.id, payload);

  if (wasUpdated) {
    closeEditSubscription();
  }
}

async function confirmDeleteSubscription(): Promise<void> {
  if (!selectedSubscription.value) {
    return;
  }

  const wasDeleted = await deleteSubscription(selectedSubscription.value.id);

  if (wasDeleted) {
    closeDeleteSubscription();
  }
}

function handleCreateFormStateChange(state: FormState): void {
  createFormState.value = state;
}

function handleEditFormStateChange(state: FormState): void {
  editFormState.value = state;
}

function formatDateLabel(date: string | null): string {
  if (!date) {
    return 'Sin fecha';
  }

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${date}T00:00:00`));
}
</script>

<template>
  <div class="space-y-5">
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
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
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

    <section v-if="loadError && hasSubscriptions" class="rounded-2xl border border-(--app-color-danger) px-4 py-3">
      <AppText class="text-(--app-color-danger)!">
        {{ loadError }}
      </AppText>
    </section>

    <section v-if="isLoading && !hasSubscriptions" class="rounded-2xl border px-4 py-10 text-center">
      <AppText>Cargando suscripciones...</AppText>
    </section>

    <section
      v-else-if="loadError && !hasSubscriptions"
      class="space-y-3 rounded-2xl border px-4 py-6 text-center"
    >
      <AppText>{{ loadError }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="outline" @click="loadSubscriptions">
          Reintentar
        </AppButton>
      </div>
    </section>

    <section v-else class="space-y-3">
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
          :cycle="formatSubscriptionFrequency(subscription.frequencyUnit, subscription.frequencyType)"
          :item-id="subscription.id"
          :next-charge="formatDateLabel(subscription.nextPaymentDate)"
          :plan="subscription.name"
          :status="subscription.finishedAt ? 'cancelled' : 'active'"
          @delete="openDeleteSubscription"
          @edit="openEditSubscription"
        />

        <div
          v-if="filteredSubscriptions.length === 0"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">
            No hay suscripciones que coincidan con la búsqueda o los filtros actuales.
          </AppText>
        </div>

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
          <AppTitle as="h2" size="sm">Frecuencia</AppTitle>
          <AppText>Refina la lista por la unidad principal de recurrencia.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="unit in subscriptionUnitOptions"
            :key="unit.value"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
            :class="
              selectedUnits.includes(unit.value)
                ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
            "
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="toggleUnit(unit.value)"
          >
            {{ unit.label }}
          </button>
        </div>
      </div>
    </AppModal>

    <AppModal
      :open="isCreateSubscriptionOpen"
      :actions="createSubscriptionActions"
      title="Nueva suscripción"
      variant="default"
      @close="closeCreateSubscription"
    >
      <SubscriptionsForm
        form-id="subscription-form"
        :funding-account-options="fundingAccountOptions"
        :server-error="saveError"
        @state-change="handleCreateFormStateChange"
        @submit="handleCreateSubscriptionSubmit"
      />
    </AppModal>

    <AppModal
      :open="isEditSubscriptionOpen"
      :actions="editSubscriptionActions"
      title="Editar suscripción"
      variant="default"
      @close="closeEditSubscription"
    >
      <SubscriptionsForm
        v-if="selectedSubscription"
        form-id="edit-subscription-form"
        :initial-values="selectedSubscription"
        :funding-account-options="fundingAccountOptions"
        :server-error="saveError"
        @state-change="handleEditFormStateChange"
        @submit="handleEditSubscriptionSubmit"
      />
    </AppModal>

    <AppModal
      :open="isDeleteSubscriptionOpen"
      :actions="deleteSubscriptionActions"
      title="Eliminar suscripción"
      variant="danger"
      @action="$event === 'confirm-delete-subscription' && confirmDeleteSubscription()"
      @close="closeDeleteSubscription"
    >
      <div class="space-y-3">
        <AppText v-if="selectedSubscription">
          Vas a eliminar
          <strong>{{ selectedSubscription.name }}</strong
          >.
        </AppText>
        <AppText v-if="deleteError" class="text-(--app-color-danger)!">
          {{ deleteError }}
        </AppText>
      </div>
    </AppModal>
  </div>
</template>
