<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import FacilityInvitationActionModal from '@/modules/admin/components/FacilityInvitationActionModal.vue';
import FacilityInvitationsList from '@/modules/admin/components/FacilityInvitationsList.vue';
import FacilityInvitationsToolbar from '@/modules/admin/components/FacilityInvitationsToolbar.vue';
import { createAccountInvitesRepository } from '@/modules/accounts/repositories/accountInvitesRepository';
import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';
import { ApiError } from '@/lib/api/apiClient';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import { areQueriesEqual } from '@/modules/shared/lib/queryParams';
import { AppListState, AppLoadMoreFooter, AppText } from '@/modules/shared/components';

type PendingInvitationAction = 'accepted' | 'declined';

const accountInvitesRepository = createAccountInvitesRepository();
const route = useRoute();
const router = useRouter();
const defaultInvitationsPerPage = 20;

const searchTerm = ref('');
const isSaving = ref(false);
const saveError = ref<string | null>(null);
const selectedInvitationId = ref<string | null>(null);
const pendingAction = ref<PendingInvitationAction | null>(null);

const invitationsPerPage = computed(() => {
  const rawValue =
    typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultInvitationsPerPage;
  }

  return rawValue;
});

const invitationsState = usePaginatedCollection<AccountInvite, []>({
  defaultPerPage: defaultInvitationsPerPage,
  loadPage: (options) =>
    accountInvitesRepository.listAll({
      ...options,
      filters: {
        search: searchTerm.value.trim() || undefined,
        status: ['pending'],
      },
    }),
  resolveErrorMessage,
  loadErrorMessage: 'No fue posible cargar las invitaciones.',
  loadMoreErrorMessage: 'No fue posible cargar más invitaciones.',
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(
    () =>
      !invitationsState.isLoading.value &&
      !invitationsState.isLoadingMore.value &&
      invitationsState.hasMoreItems.value,
  ),
  onIntersect: () => {
    void invitationsState.loadMore();
  },
});

const selectedInvitation = computed(() => {
  if (!selectedInvitationId.value) {
    return null;
  }

  return (
    invitationsState.items.value.find(
      (invitation) => invitation.id === selectedInvitationId.value,
    ) ?? null
  );
});

const visibleInvitations = computed(() => invitationsState.items.value);

const actionModalActions = computed(() => [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-invitation-action',
    label: isSaving.value
      ? 'Guardando...'
      : pendingAction.value === 'accepted'
        ? 'Aceptar invitación'
        : 'Rechazar invitación',
    tone: 'primary' as const,
    disabled: !selectedInvitation.value || !pendingAction.value || isSaving.value,
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

async function loadInvitations(): Promise<void> {
  await invitationsState.load([], {
    reset: true,
    perPage: invitationsPerPage.value,
  });
}

watch(
  () => route.query.search,
  (nextSearch) => {
    searchTerm.value = typeof nextSearch === 'string' ? nextSearch : '';
  },
  { immediate: true },
);

watch(searchTerm, () => {
  const nextQuery = {
    ...route.query,
    search: searchTerm.value.trim() || undefined,
  };

  if (!areQueriesEqual(route.query, nextQuery)) {
    void router.replace({ query: nextQuery });
  }

  void loadInvitations();
});

watch(
  invitationsPerPage,
  () => {
    void loadInvitations();
  },
  { immediate: true },
);

function openInvitationAction(inviteId: string, action: PendingInvitationAction): void {
  saveError.value = null;
  selectedInvitationId.value = inviteId;
  pendingAction.value = action;
}

function closeInvitationAction(): void {
  selectedInvitationId.value = null;
  pendingAction.value = null;
  saveError.value = null;
}

async function confirmInvitationAction(): Promise<void> {
  if (!selectedInvitation.value || !pendingAction.value) {
    return;
  }

  isSaving.value = true;
  saveError.value = null;

  try {
    const updatedInvitation = await accountInvitesRepository.respond(selectedInvitation.value.id, {
      accountId: selectedInvitation.value.accountId,
      email: selectedInvitation.value.email,
      percentage: selectedInvitation.value.percentage,
      status: pendingAction.value,
    });

    invitationsState.setItems(
      invitationsState.items.value.map((invitation) =>
        invitation.id === updatedInvitation.id ? updatedInvitation : invitation,
      ),
    );

    closeInvitationAction();
  } catch (error) {
    saveError.value = resolveErrorMessage(error, 'No fue posible responder la invitación.');
  } finally {
    isSaving.value = false;
  }
}

function reloadInvitations(): void {
  void loadInvitations();
}

function handleLoadMoreRetry(): void {
  void invitationsState.loadMore();
}

function infiniteStatusLabel(): string {
  if (invitationsState.isLoadingMore.value) {
    return 'Cargando más invitaciones...';
  }

  if (invitationsState.hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para revisar más invitaciones conforme se compartan nuevas cuentas.';
}
</script>

<template>
  <section class="space-y-4">
    <FacilityInvitationsToolbar v-model:search-term="searchTerm" />

    <section
      v-if="invitationsState.loadError.value && invitationsState.items.value.length > 0"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ invitationsState.loadError.value }}</AppText>
    </section>

    <AppListState
      :error="invitationsState.loadError.value"
      :has-items="invitationsState.items.value.length > 0"
      :is-loading="invitationsState.isLoading.value"
      loading-label="Cargando invitaciones..."
      @retry="reloadInvitations"
    >
      <FacilityInvitationsList
        :invitations="visibleInvitations"
        @accept="openInvitationAction($event, 'accepted')"
        @reject="openInvitationAction($event, 'declined')"
      >
        <template #footer>
          <div ref="loadMoreSentinel">
            <AppLoadMoreFooter
              :label="infiniteStatusLabel()"
              :show-retry="
                Boolean(invitationsState.loadError.value && invitationsState.items.value.length > 0)
              "
              @retry="handleLoadMoreRetry"
            />
          </div>
        </template>
      </FacilityInvitationsList>
    </AppListState>

    <FacilityInvitationActionModal
      :actions="actionModalActions"
      :invitation="selectedInvitation"
      :pending-action="pendingAction"
      :save-error="saveError"
      @close="closeInvitationAction"
      @confirm="confirmInvitationAction"
    />
  </section>
</template>
