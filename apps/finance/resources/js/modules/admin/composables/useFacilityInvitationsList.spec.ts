import { ref } from 'vue';
import { describe, expect, it, vi } from 'vitest';

import { useFacilityInvitationsList } from './useFacilityInvitationsList';

const { listAll } = vi.hoisted(() => ({ listAll: vi.fn() }));

vi.mock('@/modules/accounts/repositories/accountInvitesRepository', () => ({
  createAccountInvitesRepository: () => ({ listAll }),
}));

vi.mock('@/modules/shared/composables/usePaginatedCollection', () => ({
  usePaginatedCollection: (options: {
    loadPage: (pageOptions: { page: number; perPage: number }) => Promise<unknown>;
  }) => ({
    load: (_args: [], paging: { perPage: number }) =>
      options.loadPage({ page: 1, perPage: paging.perPage }),
  }),
}));

describe('useFacilityInvitationsList', () => {
  it('loads all received invitation statuses while preserving search and pagination', async () => {
    listAll.mockResolvedValue({ items: [] });
    const state = useFacilityInvitationsList({
      defaultPerPage: 20,
      perPage: ref(10),
      searchTerm: ref(' Home '),
    });

    await state.loadInvitations();

    expect(listAll).toHaveBeenCalledWith({
      page: 1,
      perPage: 10,
      filters: { search: 'Home' },
    });
  });
});
