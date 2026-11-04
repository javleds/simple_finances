import { computed, ref } from 'vue';
import { describe, expect, it, vi } from 'vitest';

import type { AccountGoal, AccountGoalWritePayload } from '../schemas/accountGoalSchemas';
import { useAccountGoalPageActions } from './useAccountGoalPageActions';

describe('goal submissions', () => {
    it('ignores repeated submission while a save is pending and allows a retry afterwards', async () => {
        const isSaving = ref(false);
        let finishSave: (result: boolean) => void = () => {};
        const createGoal = vi.fn<() => Promise<boolean>>(() => {
            isSaving.value = true;
            return new Promise<boolean>((resolve) => {
                finishSave = resolve;
            });
        });
        const closeCreateGoal = vi.fn<() => void>();
        const actions = useAccountGoalPageActions({
            accountId: computed(() => 'account'),
            activeFilters: computed(() => ({})),
            closeCreateGoal,
            closeDeleteGoal: vi.fn<() => void>(),
            closeEditGoal: vi.fn<() => void>(),
            createGoal,
            deleteGoal: vi.fn<() => Promise<boolean>>(),
            goalsPerPage: computed(() => 20),
            loadGoals: vi.fn<() => Promise<void>>(),
            loadMoreGoals: vi.fn<() => Promise<void>>(),
            selectedGoal: ref<AccountGoal | null>(null),
            updateGoal: vi.fn<() => Promise<boolean>>(),
            isSaving,
        });
        const payload: AccountGoalWritePayload = {
            accountId: 'account',
            name: 'Emergency fund',
            amount: 1000,
            deadline: null,
            status: 'in progress',
        };
        const first = actions.handleCreateGoalSubmit(payload);
        await actions.handleCreateGoalSubmit(payload);
        expect(createGoal).toHaveBeenCalledTimes(1);
        finishSave(false);
        await first;
        expect(closeCreateGoal).not.toHaveBeenCalled();

        isSaving.value = false;
        const retry = actions.handleCreateGoalSubmit(payload);
        expect(createGoal).toHaveBeenCalledTimes(2);
        finishSave(true);
        await retry;
        expect(closeCreateGoal).toHaveBeenCalledTimes(1);
    });
});
