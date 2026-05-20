<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import AccountInvitationForm from '@/modules/accounts/components/AccountInvitationForm.vue';
import AccountInvitationListItem from '@/modules/accounts/components/AccountInvitationListItem.vue';
import { useAccountInvitesCrud } from '@/modules/accounts/composables/useAccountInvitesCrud';
import type {
  AccountInviteStatus,
  AccountInviteWritePayload,
} from '@/modules/accounts/schemas/accountInviteSchemas';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';
import {
  AppButton,
  AppIconButton,
  AppInput,
  AppModal,
  AppSectionBar,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

const route = useRoute();
const router = useRouter();
const searchTerm = ref('');
const isFiltersOpen = ref(false);
const isCreateInvitationOpen = ref(false);
const isEditInvitationOpen = ref(false);
const isDeleteInvitationOpen = ref(false);
const selectedStatuses = ref<AccountInviteStatus[]>([]);
const selectedInvitationId = ref<string | null>(null);
const createFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });
const editFormState = ref<FormState>({ canSubmit: false, isSubmitting: false });

const invitationStatusOptions = [
  { value: 'pending', label: 'Pendiente' },
  { value: 'accepted', label: 'Aceptada' },
  { value: 'declined', label: 'Declinada' },
] as const;
const defaultInvitesPerPage = 20;

const accountId = computed(() =>
  typeof route.params.accountId === 'string' ? route.params.accountId : '',
);

const {
  invites,
  hasInvites,
  hasMoreInvites,
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
  loadInvites,
  loadMoreInvites,
  createInvite,
  updateInvite,
  deleteInvite,
} = useAccountInvitesCrud();

const invitationsPerPage = computed(() => {
  const rawValue = typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultInvitesPerPage;
  }

  return rawValue;
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreInvites.value),
  onIntersect: () => {
    void loadMoreInvites();
  },
});

const activeFilters = computed(() => ({
  search: searchTerm.value.trim() || undefined,
  status: selectedStatuses.value.length > 0 ? [...selectedStatuses.value] : undefined,
}));

const selectedInvitation = computed(() => {
  if (!selectedInvitationId.value) {
    return null;
  }

  return invites.value.find((invitation) => invitation.id === selectedInvitationId.value) ?? null;
});

const createInviteActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-invitation',
    label: isSaving.value ? 'Guardando...' : 'Crear invitación',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'account-invitation-form',
    disabled: !createFormState.value.canSubmit || isSaving.value,
  },
]);

const editInviteActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'submit-edit-invitation',
    label: isSaving.value ? 'Guardando...' : 'Guardar cambios',
    tone: 'primary' as const,
    type: 'submit' as const,
    form: 'edit-account-invitation-form',
    disabled: !editFormState.value.canSubmit || isSaving.value,
  },
]);

const deleteInviteActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-delete-invitation',
    label: isDeleting.value ? 'Eliminando...' : 'Eliminar invitación',
    tone: 'primary' as const,
    disabled: !selectedInvitation.value || isDeleting.value,
  },
]);

watch(
  () => route.query,
  (nextQuery) => {
    searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
    selectedStatuses.value = parseQueryValues(nextQuery.status, isInvitationStatus);
  },
  { immediate: true },
);

watch(
  [searchTerm, selectedStatuses],
  () => {
    const nextQuery = {
      ...route.query,
      search: searchTerm.value.trim() || undefined,
      status: selectedStatuses.value.length > 0 ? selectedStatuses.value.join(',') : undefined,
    };

    if (areQueriesEqual(route.query, nextQuery)) {
      return;
    }

    void router.replace({ query: nextQuery });
  },
  { deep: true },
);

watch(
  [accountId, activeFilters, invitationsPerPage],
  ([nextAccountId, nextFilters, nextPerPage]) => {
    if (!nextAccountId) {
      return;
    }

    void loadInvites(nextAccountId, nextFilters, {
      reset: true,
      perPage: nextPerPage,
    });
  },
  { immediate: true },
);

function formatDateLabel(date: string | null): string {
  if (!date) {
    return 'Sin fecha';
  }

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(date));
}

function openFilters(): void {
  isFiltersOpen.value = true;
}

function closeFilters(): void {
  isFiltersOpen.value = false;
}

function clearFilters(): void {
  selectedStatuses.value = [];
}

function toggleStatus(status: AccountInviteStatus): void {
  if (selectedStatuses.value.includes(status)) {
    selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
    return;
  }

  selectedStatuses.value = [...selectedStatuses.value, status];
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
  clearSaveError();
  createFormState.value = { canSubmit: false, isSubmitting: false };
  isCreateInvitationOpen.value = true;
}

function closeCreateInvitation(): void {
  isCreateInvitationOpen.value = false;
  clearSaveError();
}

function openEditInvitation(invitationId: string): void {
  clearSaveError();
  selectedInvitationId.value = invitationId;
  editFormState.value = { canSubmit: false, isSubmitting: false };
  isEditInvitationOpen.value = true;
}

function closeEditInvitation(): void {
  isEditInvitationOpen.value = false;
  selectedInvitationId.value = null;
  clearSaveError();
}

function openDeleteInvitation(invitationId: string): void {
  clearDeleteError();
  selectedInvitationId.value = invitationId;
  isDeleteInvitationOpen.value = true;
}

function closeDeleteInvitation(): void {
  isDeleteInvitationOpen.value = false;
  selectedInvitationId.value = null;
  clearDeleteError();
}

async function handleCreateInvitationSubmit(payload: AccountInviteWritePayload): Promise<void> {
  const wasCreated = await createInvite(payload);

  if (wasCreated) {
    closeCreateInvitation();
  }
}

async function handleEditInvitationSubmit(payload: AccountInviteWritePayload): Promise<void> {
  if (!selectedInvitation.value) {
    return;
  }

  const wasUpdated = await updateInvite(selectedInvitation.value.id, payload);

  if (wasUpdated) {
    closeEditInvitation();
  }
}

async function confirmDeleteInvitation(): Promise<void> {
  if (!selectedInvitation.value) {
    return;
  }

  const wasDeleted = await deleteInvite(selectedInvitation.value.id, accountId.value);

  if (wasDeleted) {
    closeDeleteInvitation();
  }
}

function handleCreateFormStateChange(state: FormState): void {
  createFormState.value = state;
}

function handleEditFormStateChange(state: FormState): void {
  editFormState.value = state;
}

function reloadInvitations(): void {
  if (!accountId.value) {
    return;
  }

  void loadInvites(accountId.value, activeFilters.value, {
    reset: true,
    perPage: invitationsPerPage.value,
  });
}

function isInvitationStatus(value: string): value is AccountInviteStatus {
  return value === 'pending' || value === 'accepted' || value === 'declined';
}

function handleLoadMoreRetry(): void {
  void loadMoreInvites();
}

function infiniteStatusLabel(): string {
  if (isLoadingMore.value) {
    return 'Cargando más invitaciones...';
  }

  if (hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para revisar más invitaciones conforme se amplíe la colaboración.';
}
</script>

<template>
  <section class="space-y-4">
    <AppSectionBar
      title="Invitaciones"
      description="Invita usuarios a colaborar dentro de esta cuenta."
    >
      <template #actions>
        <AppButton variant="primary" @click="openCreateInvitation">
          <PlusIcon class="h-4 w-4" />
        </AppButton>
      </template>
    </AppSectionBar>

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
        >
          <MagnifyingGlassIcon class="h-5 w-5" />
        </div>
        <AppInput
          id="invitation-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar invitación por correo"
          class="pl-11"
        />
      </div>

      <AppIconButton ariaLabel="Abrir filtros avanzados" @click="openFilters">
        <AdjustmentsHorizontalIcon class="h-5 w-5" />
      </AppIconButton>
    </div>

    <section
      v-if="loadError && hasInvites"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <section v-if="isLoading && !hasInvites" class="rounded-2xl border px-4 py-10 text-center">
      <AppText>Cargando invitaciones...</AppText>
    </section>

    <section
      v-else-if="loadError && !hasInvites"
      class="space-y-3 rounded-2xl border px-4 py-6 text-center"
    >
      <AppText>{{ loadError }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="secondary" @click="reloadInvitations">Reintentar</AppButton>
      </div>
    </section>

    <section v-else class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <AppText size="sm" tone="subtle"
          >{{ invites.length }} invitaciones visibles</AppText
        >
        <AppText size="sm" tone="subtle">Scroll continuo</AppText>
      </div>

      <div class="space-y-4">
        <AccountInvitationListItem
          v-for="invitation in invites"
          :key="invitation.id"
          :email="invitation.email"
          :item-id="invitation.id"
          :meta-label="formatDateLabel(invitation.invitedAt)"
          :percentage-label="`${invitation.percentage}%`"
          :status="invitation.status"
          @delete="openDeleteInvitation"
        />

        <div
          v-if="invites.length === 0"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm"
            >No hay invitaciones que coincidan con la búsqueda o los filtros actuales.</AppText
          >
        </div>

        <div
          ref="loadMoreSentinel"
          class="rounded-2xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">{{ infiniteStatusLabel() }}</AppText>
          <div v-if="loadError && hasInvites" class="mt-3 flex justify-center">
            <AppButton variant="secondary" @click="handleLoadMoreRetry">Reintentar</AppButton>
          </div>
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
          <AppText>Refina las invitaciones según su estado de respuesta.</AppText>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in invitationStatusOptions"
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
      </div>
    </AppModal>

    <AppModal
      :open="isCreateInvitationOpen"
      :actions="createInviteActions"
      title="Nueva invitación"
      variant="default"
      @close="closeCreateInvitation"
    >
      <AccountInvitationForm
        v-if="accountId"
        form-id="account-invitation-form"
        :account-id="accountId"
        :server-error="saveError"
        @state-change="handleCreateFormStateChange"
        @submit="handleCreateInvitationSubmit"
      />
    </AppModal>

    <AppModal
      :open="isEditInvitationOpen"
      :actions="editInviteActions"
      title="Editar invitación"
      variant="default"
      @close="closeEditInvitation"
    >
      <AccountInvitationForm
        v-if="accountId && selectedInvitation"
        form-id="edit-account-invitation-form"
        :account-id="accountId"
        :initial-values="selectedInvitation"
        :server-error="saveError"
        @state-change="handleEditFormStateChange"
        @submit="handleEditInvitationSubmit"
      />
    </AppModal>

    <AppModal
      :open="isDeleteInvitationOpen"
      :actions="deleteInviteActions"
      title="Eliminar invitación"
      variant="danger"
      @action="$event === 'confirm-delete-invitation' && confirmDeleteInvitation()"
      @close="closeDeleteInvitation"
    >
      <div class="space-y-3">
        <AppText v-if="selectedInvitation">
          Vas a eliminar la invitación de
          <strong>{{ selectedInvitation.email }}</strong
          >.
        </AppText>
        <AppText v-if="deleteError" class="text-(--app-color-danger)!">{{ deleteError }}</AppText>
      </div>
    </AppModal>
  </section>
</template>
