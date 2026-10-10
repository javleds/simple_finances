import type { Ref } from 'vue';

import { createAccountInvitesRepository } from '@/modules/accounts/repositories/accountInvitesRepository';
import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';
import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

type UseFacilityInvitationsListOptions = {
  defaultPerPage: number;
  perPage: Ref<number>;
  searchTerm: Ref<string>;
};

const accountInvitesRepository = createAccountInvitesRepository();

export function useFacilityInvitationsList(options: UseFacilityInvitationsListOptions) {
  const invitationsState = usePaginatedCollection<AccountInvite, []>({
    defaultPerPage: options.defaultPerPage,
    loadPage: (pageOptions) =>
      accountInvitesRepository.listAll({
        ...pageOptions,
        filters: {
          search: options.searchTerm.value.trim() || undefined,
        },
      }),
    resolveErrorMessage: resolveApiErrorMessage,
    loadErrorMessage: 'No fue posible cargar las invitaciones.',
    loadMoreErrorMessage: 'No fue posible cargar más invitaciones.',
  });

  async function loadInvitations(): Promise<void> {
    await invitationsState.load([], {
      reset: true,
      perPage: options.perPage.value,
    });
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

  return {
    invitationsState,
    loadInvitations,
    reloadInvitations,
    handleLoadMoreRetry,
    infiniteStatusLabel,
  };
}
