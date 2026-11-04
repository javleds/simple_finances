import type { SubscriptionListFilters, SubscriptionWritePayload } from '../types';

export function buildSubscriptionWritePayload(payload: SubscriptionWritePayload) {
  return {
    name: payload.name,
    amount: payload.amount,
    started_at: payload.startDate,
    frequency_unit: payload.frequencyUnit,
    frequency_type: payload.frequencyType,
    finished_at: payload.finishedAt,
    feed_account_id: payload.fundingAccountId,
  };
}

export function mapSubscriptionStatusesToFinishedFilter(
  statuses: SubscriptionListFilters['status'],
): number | undefined {
  if (!statuses || statuses.length === 0 || statuses.length > 1) {
    return undefined;
  }

  return statuses[0] === 'active' ? 0 : 1;
}
