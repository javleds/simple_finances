import { describe, expect, it } from 'vitest';

import { mapAccountStatusesToDeletedAtFilter } from './accountsRepository';

describe('accounts repository filters', () => {
  it('maps account status filters to the API deleted_at flag', () => {
    expect(mapAccountStatusesToDeletedAtFilter(['Activo'])).toEqual(['false']);
    expect(mapAccountStatusesToDeletedAtFilter(['Inactivo'])).toEqual(['true']);
    expect(mapAccountStatusesToDeletedAtFilter(['Activo', 'Inactivo'])).toBeUndefined();
    expect(mapAccountStatusesToDeletedAtFilter(undefined)).toBeUndefined();
  });
});
