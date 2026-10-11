import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, ref, watch } from 'vue';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';
import { personalMoneyRepository } from '../repositories/personalMoneyRepository';

export const personalMoneyQueryKeys = { all: ['personal-money'] as const };

export function usePersonalMoney() {
    const queryClient = useQueryClient();
    const page = ref(1);
    const resolution = ref<'all' | 'pending'>('all');
    const mutationError = ref<string | null>(null);
    watch(resolution, () => {
        page.value = 1;
    });
    const query = useQuery({
        queryKey: computed(() => [...personalMoneyQueryKeys.all, page.value, resolution.value]),
        queryFn: () => personalMoneyRepository.get(page.value, resolution.value),
    });
    async function refresh(): Promise<void> {
        await queryClient.invalidateQueries({ queryKey: personalMoneyQueryKeys.all });
    }
    function onError(error: unknown): void {
        mutationError.value = resolveApiErrorMessage(error, 'No fue posible guardar los cambios.');
    }
    const settingsMutation = useMutation({
        mutationFn: personalMoneyRepository.saveSettings,
        onMutate: () => {
            mutationError.value = null;
        },
        onSuccess: async () => {
            page.value = 1;
            await refresh();
        },
        onError,
    });
    const linkMutation = useMutation({
        mutationFn: personalMoneyRepository.saveLink,
        onMutate: () => {
            mutationError.value = null;
        },
        onSuccess: refresh,
        onError,
    });
    watch(
        () => query.data.value?.meta.last_page,
        (lastPage) => {
            if (lastPage && page.value > lastPage) page.value = lastPage;
        },
    );
    return {
        state: query.data,
        isLoading: query.isPending,
        loadError: computed(() =>
            query.error.value
                ? resolveApiErrorMessage(query.error.value, 'No fue posible cargar tu disponible.')
                : null,
        ),
        mutationError,
        isSaving: computed(() => settingsMutation.isPending.value || linkMutation.isPending.value),
        page,
        resolution,
        retry: query.refetch,
        saveSettings: settingsMutation.mutateAsync,
        saveLink: linkMutation.mutateAsync,
    };
}
