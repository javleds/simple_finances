<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';
import SubscriptionDeleteModal from '@/modules/subscriptions/components/SubscriptionDeleteModal.vue';
import SubscriptionFiltersModal from '@/modules/subscriptions/components/SubscriptionFiltersModal.vue';
import SubscriptionFormModal from '@/modules/subscriptions/components/SubscriptionFormModal.vue';
import SubscriptionsList from '@/modules/subscriptions/components/SubscriptionsList.vue';
import SubscriptionsToolbar from '@/modules/subscriptions/components/SubscriptionsToolbar.vue';
import { useSubscriptionFilters } from '@/modules/subscriptions/composables/useSubscriptionFilters';
import { useSubscriptionModalActions } from '@/modules/subscriptions/composables/useSubscriptionModalActions';
import { useSubscriptionModals } from '@/modules/subscriptions/composables/useSubscriptionModals';
import { useSubscriptionsCrud } from '@/modules/subscriptions/composables/useSubscriptionsCrud';
import type {
  SubscriptionFrequencyType,
  SubscriptionStatusFilter,
  SubscriptionWritePayload,
} from '@/modules/subscriptions/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import {
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const accountsRepository = createAccountsRepository();
const route = useRoute();

const fundingAccountOptions = ref<Array<{ value: string; label: string; description?: string }>>(
  [],
);

const subscriptionStatusOptions = [
  { value: 'active', label: 'Activa' },
  { value: 'cancelled', label: 'Cancelada' },
] as const;

const subscriptionUnitOptions = [
  { value: 'days', label: 'Días' },
  { value: 'months', label: 'Meses' },
  { value: 'years', label: 'Años' },
] as const;
const defaultSubscriptionsPerPage = 20;
const {
  activeFilters,
  clearFilters,
  searchTerm,
  selectedStatuses,
  selectedUnits,
  toggleStatus,
  toggleUnit,
} = useSubscriptionFilters();

const {
  subscriptions,
  hasSubscriptions,
  hasMoreSubscriptions,
  hasReachedEnd,
  isLoading,
  isLoadingMore,
  isSaving,
  isDeleting,
  loadError,
  saveError,
  deleteError,
  clearSaveError,
  clearDeleteError,
  loadSubscriptions,
  loadMoreSubscriptions,
  createSubscription,
  updateSubscription,
  deleteSubscription,
} = useSubscriptionsCrud();

const {
  closeCreateSubscription,
  closeDeleteSubscription,
  closeEditSubscription,
  closeFilters,
  createFormState,
  editFormState,
  handleCreateFormStateChange,
  handleEditFormStateChange,
  isCreateSubscriptionOpen,
  isDeleteSubscriptionOpen,
  isEditSubscriptionOpen,
  isFiltersOpen,
  openCreateSubscription,
  openDeleteSubscription,
  openEditSubscription,
  openFilters,
  selectedSubscription,
} = useSubscriptionModals({
  clearDeleteError,
  clearSaveError,
  subscriptions,
});

const {
  createSubscriptionActions,
  deleteSubscriptionActions,
  editSubscriptionActions,
} = useSubscriptionModalActions({
  createFormState,
  editFormState,
  isDeleting,
  isSaving,
  selectedSubscription,
});

const subscriptionsPerPage = computed(() => {
  const rawValue =
    typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultSubscriptionsPerPage;
  }

  return rawValue;
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreSubscriptions.value),
  onIntersect: () => {
    void loadMoreSubscriptions();
  },
});

onMounted(() => {
  void loadFundingAccounts();
});

watch(
  [activeFilters, subscriptionsPerPage],
  ([nextFilters, nextPerPage]) => {
    void loadSubscriptions(nextFilters, {
      reset: true,
      perPage: nextPerPage,
    });
  },
  { immediate: true },
);

async function loadFundingAccounts(): Promise<void> {
  try {
    const response = await accountsRepository.list();
    fundingAccountOptions.value = response.items.map((account) => ({
      value: account.id,
      label: account.name,
      description: account.description,
    }));
  } catch {
    fundingAccountOptions.value = [];
  }
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

function reloadSubscriptions(): void {
  void loadSubscriptions(activeFilters.value, {
    reset: true,
    perPage: subscriptionsPerPage.value,
  });
}

function handleLoadMoreRetry(): void {
  void loadMoreSubscriptions();
}

function infiniteStatusLabel(): string {
  if (isLoadingMore.value) {
    return 'Cargando más suscripciones...';
  }

  if (hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para revisar más planes y complementos conforme crezca la cobertura contratada.';
}
</script>

<template>
  <div class="space-y-5">
    <SubscriptionsToolbar
      v-model:search-term="searchTerm"
      @create="openCreateSubscription"
      @open-filters="openFilters"
    />

    <section
      v-if="loadError && hasSubscriptions"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">
        {{ loadError }}
      </AppText>
    </section>

    <AppListState
      :error="loadError"
      :has-items="hasSubscriptions"
      :is-loading="isLoading"
      loading-label="Cargando suscripciones..."
      @retry="reloadSubscriptions"
    >
      <SubscriptionsList
        :subscriptions="subscriptions"
        @delete="openDeleteSubscription"
        @edit="openEditSubscription"
      >
        <template #footer>
          <div ref="loadMoreSentinel">
            <AppLoadMoreFooter
              :label="infiniteStatusLabel()"
              :show-retry="Boolean(loadError && hasSubscriptions)"
              @retry="handleLoadMoreRetry"
            />
          </div>
        </template>
      </SubscriptionsList>
    </AppListState>

    <SubscriptionFiltersModal
      :open="isFiltersOpen"
      :selected-statuses="selectedStatuses"
      :selected-units="selectedUnits"
      :status-options="subscriptionStatusOptions"
      :unit-options="subscriptionUnitOptions"
      @clear="clearFilters"
      @close="closeFilters"
      @toggle-status="toggleStatus"
      @toggle-unit="toggleUnit"
    />

    <SubscriptionFormModal
      :open="isCreateSubscriptionOpen"
      :actions="createSubscriptionActions"
      form-id="subscription-form"
      :funding-account-options="fundingAccountOptions"
      :server-error="saveError"
      title="Nueva suscripción"
      @close="closeCreateSubscription"
      @state-change="handleCreateFormStateChange"
      @submit="handleCreateSubscriptionSubmit"
    />

    <SubscriptionFormModal
      :open="isEditSubscriptionOpen"
      :actions="editSubscriptionActions"
      form-id="edit-subscription-form"
      :funding-account-options="fundingAccountOptions"
      :initial-values="selectedSubscription"
      requires-initial-values
      :server-error="saveError"
      title="Editar suscripción"
      @close="closeEditSubscription"
      @state-change="handleEditFormStateChange"
      @submit="handleEditSubscriptionSubmit"
    />

    <SubscriptionDeleteModal
      :open="isDeleteSubscriptionOpen"
      :actions="deleteSubscriptionActions"
      :delete-error="deleteError"
      :subscription="selectedSubscription"
      @close="closeDeleteSubscription"
      @confirm="confirmDeleteSubscription"
    />
  </div>
</template>
