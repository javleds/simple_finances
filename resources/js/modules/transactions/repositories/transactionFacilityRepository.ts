import { createTransactionsRepository } from './transactionsRepository';

export function createTransactionFacilityRepository() {
  const transactionsRepository = createTransactionsRepository();

  return {
    list: transactionsRepository.listFacility,
  };
}
