<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import FacilityInvitationActionModal from '@/modules/admin/components/FacilityInvitationActionModal.vue';
import FacilityInvitationsList from '@/modules/admin/components/FacilityInvitationsList.vue';
import FacilityInvitationsToolbar from '@/modules/admin/components/FacilityInvitationsToolbar.vue';
import { useFacilityInvitationActions } from '@/modules/admin/composables/useFacilityInvitationActions';
import { useFacilityInvitationsList } from '@/modules/admin/composables/useFacilityInvitationsList';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { areQueriesEqual } from '@/modules/shared/lib/queryParams';
import { AppListState, AppLoadMoreFooter, AppText } from '@/modules/shared/components';

const route = useRoute();
const router = useRouter();
const defaultInvitationsPerPage = 20;

const searchTerm = ref('');

const invitationsPerPage = computed(() => {
  const rawValue =
    typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultInvitationsPerPage;
  }

  return rawValue;
});

const {
  invitationsState,
  loadInvitations,
  reloadInvitations,
  handleLoadMoreRetry,
  infiniteStatusLabel,
} = useFacilityInvitationsList({
  defaultPerPage: defaultInvitationsPerPage,
  perPage: invitationsPerPage,
  searchTerm,
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

const {
  selectedInvitation,
  pendingAction,
  isSaving,
  saveError,
  openInvitationAction,
  closeInvitationAction,
  confirmInvitationAction,
} = useFacilityInvitationActions({
  invitations: invitationsState.items,
  removeInvitation: invitationsState.removeItem,
  replaceInvitation: invitationsState.replaceItem,
});

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
    loading: isSaving.value,
  },
]);

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
        :invitations="invitationsState.items.value"
        @accept="openInvitationAction($event, 'accepted')"
        @reject="openInvitationAction($event, 'declined')"
      >
        <template #footer>
          <div ref="loadMoreSentinel">
            <AppLoadMoreFooter
              :label="infiniteStatusLabel()"
              :show-retry="Boolean(invitationsState.loadError.value && invitationsState.items.value.length > 0)"
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
