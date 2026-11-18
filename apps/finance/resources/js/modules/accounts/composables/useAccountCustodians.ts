import { useQueryClient } from '@tanstack/vue-query';
import { computed, ref, watch, type Ref } from 'vue';
import { dashboardQueryKeys } from '@/modules/admin/queries/dashboardQueries';
import { personalMoneyQueryKeys } from '@/modules/personal-money/composables/usePersonalMoney';
import { transactionFacilityQueryKeys } from '@/modules/transactions/queries/transactionFacilityQueries';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { accountQueryKeys } from '../queries/accountQueries';
import { createAccountsRepository } from '../repositories/accountsRepository';
import type {
    Account,
    AccountCustodianPayload,
    AccountCustodyTransfer,
    AccountVirtualCustodian,
} from '../types';

const repository = createAccountsRepository();

export function useAccountCustodians(account: Ref<Account | null>, onChanged: () => Promise<void>) {
    const queryClient = useQueryClient();
    const modalAccount = ref<Account | null>(null);
    const selectedCustodian = ref<AccountVirtualCustodian | null>(null);
    const isOpen = ref(false);
    const isSaving = ref(false);
    const isPreparing = ref(false);
    const error = ref<string | null>(null);
    const custodians = computed(() => account.value?.virtualCustodians ?? []);

    watch(
        () => account.value?.id,
        () => {
            isOpen.value = false;
            error.value = null;
        },
    );

    async function open(custodianId?: string): Promise<void> {
        if (!account.value || isPreparing.value || isSaving.value) return;
        const accountId = account.value.id;
        error.value = null;
        isPreparing.value = true;
        try {
            const latest = await repository.getById(accountId);
            if (account.value?.id !== accountId) return;
            selectedCustodian.value =
                latest.virtualCustodians?.find((item) => item.id === custodianId) ?? null;
            if (custodianId && !selectedCustodian.value)
                throw new Error('El custodio ya no está disponible.');
            modalAccount.value = latest;
            queryClient.setQueryData(accountQueryKeys.detail(accountId), latest);
            isOpen.value = true;
        } catch (cause) {
            error.value = resolveApiErrorMessage(
                cause,
                'No fue posible cargar la custodia de la cuenta.',
            );
        } finally {
            isPreparing.value = false;
        }
    }

    function close(): void {
        if (isSaving.value) return;
        isOpen.value = false;
        error.value = null;
    }

    async function save(
        payload: AccountCustodianPayload | AccountCustodyTransfer[],
    ): Promise<void> {
        if (!modalAccount.value || isSaving.value || !isOpen.value) return;
        const accountId = modalAccount.value.id;
        isSaving.value = true;
        error.value = null;
        try {
            if (Array.isArray(payload)) {
                if (!selectedCustodian.value) return;
                await repository.removeCustodian(accountId, selectedCustodian.value.id, payload);
            } else {
                await repository.createCustodian(accountId, payload);
            }
            isOpen.value = false;
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: accountQueryKeys.all }),
                queryClient.invalidateQueries({ queryKey: dashboardQueryKeys.all }),
                queryClient.invalidateQueries({ queryKey: transactionFacilityQueryKeys.all }),
                queryClient.invalidateQueries({ queryKey: personalMoneyQueryKeys.all }),
                onChanged(),
            ]);
        } catch (cause) {
            error.value = resolveApiErrorMessage(cause, 'No fue posible guardar el custodio.');
        } finally {
            isSaving.value = false;
        }
    }

    return {
        custodians,
        modalAccount,
        selectedCustodian,
        isOpen,
        isSaving,
        isPreparing,
        error,
        open,
        close,
        save,
    };
}
