<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import type {
    AccountFilterSelection,
    AccountKindFilter,
    AccountStatus,
    AccountSurfaceFilter,
} from '@/modules/accounts/types';
import { AppFilterOptions, AppFilterPanel } from '@/modules/shared/components';

type FilterOption<TValue extends string> = { value: TValue; label: string };

const props = defineProps<{
    kindOptions: readonly FilterOption<AccountKindFilter>[];
    open: boolean;
    selectedKinds: AccountKindFilter[];
    selectedStatuses: AccountStatus[];
    selectedSurfaces: AccountSurfaceFilter[];
    statusOptions: readonly AccountStatus[];
    surfaceOptions: readonly FilterOption<AccountSurfaceFilter>[];
}>();

const emit = defineEmits<{
    apply: [selection: AccountFilterSelection];
    close: [];
}>();

const draft = ref<AccountFilterSelection>({ statuses: [], kinds: [], surfaces: [] });

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        draft.value = {
            statuses: [...props.selectedStatuses],
            kinds: [...props.selectedKinds],
            surfaces: [...props.selectedSurfaces],
        };
    },
    { immediate: true },
);

function clearDraft(): void {
    draft.value = { statuses: [], kinds: [], surfaces: [] };
}

function applyDraft(): void {
    emit('apply', {
        statuses: [...draft.value.statuses],
        kinds: [...draft.value.kinds],
        surfaces: [...draft.value.surfaces],
    });
    emit('close');
}
const statusFilterOptions = computed(() =>
    props.statusOptions.map((value) => ({
        value,
        label: value === 'Activo' ? 'Activas' : 'Inactivas',
    })),
);
</script>

<template>
    <AppFilterPanel
        id="account-filters"
        :open="props.open"
        @apply="applyDraft"
        @clear="clearDraft"
        @close="emit('close')"
    >
        <AppFilterOptions v-model="draft.statuses" label="Estado" :options="statusFilterOptions" />
        <AppFilterOptions
            v-model="draft.kinds"
            label="Tipo de cuenta"
            :options="props.kindOptions"
        />
        <AppFilterOptions
            v-model="draft.surfaces"
            label="Formato"
            :options="props.surfaceOptions"
        />
    </AppFilterPanel>
</template>
