import { computed, ref, watch, type Ref } from 'vue';

import type { AccountMember } from '@/modules/accounts/types';

type UseAccountUsersSplitDraftOptions = {
  hasMoreUsers: Ref<boolean>;
  onUsersChange: (users: AccountMember[]) => void;
  setUsers: (users: AccountMember[]) => void;
  users: Ref<AccountMember[]>;
};

export function useAccountUsersSplitDraft(options: UseAccountUsersSplitDraftOptions) {
  const splitDraft = ref<Record<string, number>>({});

  const splitUsers = computed(() =>
    options.users.value.map((user) => ({
      id: user.id,
      name: user.name,
    })),
  );
  const canShowSplitEditor = computed(() => splitUsers.value.length > 1);
  const hasLoadedEveryUserForSplit = computed(() => !options.hasMoreUsers.value);
  const hasSplitChanges = computed(
    () =>
      !areAllocationRecordsEqual(
        splitDraft.value,
        createAllocationRecord(options.users.value),
      ),
  );

  watch(
    () => options.users.value,
    (nextUsers) => {
      const nextRecord = createAllocationRecord(nextUsers);

      if (!hasSplitChanges.value) {
        splitDraft.value = nextRecord;
      } else {
        splitDraft.value = mergeAllocationRecords(splitDraft.value, nextRecord);
      }

      options.onUsersChange(nextUsers);
    },
    { immediate: true, deep: true },
  );

  function applySplitDraft(): void {
    options.setUsers(
      options.users.value.map((user) => ({
        ...user,
        allocationPercentage: splitDraft.value[user.id] ?? user.allocationPercentage,
      })),
    );
  }

  function resetSplitDraft(): void {
    splitDraft.value = createAllocationRecord(options.users.value);
  }

  return {
    applySplitDraft,
    canShowSplitEditor,
    hasLoadedEveryUserForSplit,
    hasSplitChanges,
    resetSplitDraft,
    splitDraft,
    splitUsers,
  };
}

function createAllocationRecord(users: ReadonlyArray<AccountMember>): Record<string, number> {
  return users.reduce<Record<string, number>>((accumulator, user) => {
    accumulator[user.id] = Number((user.allocationPercentage ?? 0).toFixed(2));
    return accumulator;
  }, {});
}

function mergeAllocationRecords(
  currentRecord: Record<string, number>,
  nextRecord: Record<string, number>,
): Record<string, number> {
  return Object.keys(nextRecord).reduce<Record<string, number>>((accumulator, userId) => {
    accumulator[userId] = currentRecord[userId] ?? nextRecord[userId] ?? 0;
    return accumulator;
  }, {});
}

function areAllocationRecordsEqual(
  left: Record<string, number>,
  right: Record<string, number>,
): boolean {
  const allKeys = new Set([...Object.keys(left), ...Object.keys(right)]);

  return [...allKeys].every((key) => {
    const leftValue = Number((left[key] ?? 0).toFixed(2));
    const rightValue = Number((right[key] ?? 0).toFixed(2));
    return leftValue === rightValue;
  });
}
