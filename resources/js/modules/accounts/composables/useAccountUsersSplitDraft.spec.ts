import { effectScope, nextTick, ref } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import type { AccountMember } from '@/modules/accounts/types';
import { useAccountUsersSplitDraft } from './useAccountUsersSplitDraft';

function member(id: string, allocationPercentage: number): AccountMember {
  return { id, name: id, email: `${id}@example.com`, allocationPercentage, pendingExpenses: 0, custodyAmount: 0, settlementAmount: 0 };
}

function setup() {
  const users = ref([member('1', 60), member('2', 40)]);
  const hasMoreUsers = ref(false);
  const savePercentages = vi.fn(async (percentages: Record<string, number>) => {
    users.value = users.value.map(user => ({ ...user, allocationPercentage: percentages[user.id]! }));
    return true;
  });
  const scope = effectScope();
  const draft = scope.run(() => useAccountUsersSplitDraft({ users, hasMoreUsers, savePercentages, onUsersChange: vi.fn() }))!;
  return { ...draft, users, hasMoreUsers, savePercentages, scope };
}

describe('account users percentage draft', () => {
  it('discards edits on reset and saves the full distribution only on apply', async () => {
    const state = setup();
    state.splitDraft.value = { '1': 30, '2': 70 };
    state.resetSplitDraft();
    expect(state.splitDraft.value).toEqual({ '1': 60, '2': 40 });
    expect(state.savePercentages).not.toHaveBeenCalled();
    state.splitDraft.value = { '1': 30, '2': 70 };
    await state.applySplitDraft();
    await nextTick();
    expect(state.savePercentages).toHaveBeenCalledWith({ '1': 30, '2': 70 });
    expect(state.hasSplitChanges.value).toBe(false);
    state.splitDraft.value = { '1': 50, '2': 50 };
    state.resetSplitDraft();
    expect(state.splitDraft.value).toEqual({ '1': 30, '2': 70 });
    state.scope.stop();
  });

  it('retains pending edits after a failed save and rejects incomplete lists', async () => {
    const state = setup();
    state.splitDraft.value = { '1': 30, '2': 70 };
    state.hasMoreUsers.value = true;
    await state.applySplitDraft();
    expect(state.savePercentages).not.toHaveBeenCalled();
    state.hasMoreUsers.value = false;
    state.savePercentages.mockResolvedValueOnce(false);
    await state.applySplitDraft();
    expect(state.splitDraft.value).toEqual({ '1': 30, '2': 70 });
    expect(state.users.value[0]?.allocationPercentage).toBe(60);
    state.scope.stop();
  });

  it('refreshes the draft when persisted percentages change without pending edits', async () => {
    const state = setup();
    state.users.value = [member('1', 80), member('2', 20)];
    await nextTick();
    expect(state.splitDraft.value).toEqual({ '1': 80, '2': 20 });
    expect(state.hasSplitChanges.value).toBe(false);
    state.scope.stop();
  });
});
