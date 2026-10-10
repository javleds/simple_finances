<script setup lang="ts">
import { ref, watch } from 'vue';

import type {
    AccountFilterSelection,
    AccountKindFilter,
    AccountStatus,
    AccountSurfaceFilter,
} from '@/modules/accounts/types';
import { AppButton, AppModal } from '@/modules/shared/components';

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
</script>

<template>
    <AppModal
        id="account-filters"
        :open="props.open"
        title="Filtros"
        presentation="sheet"
        @close="emit('close')"
    >
        <form id="account-filters-form" class="grid gap-6 pt-2" @submit.prevent="applyDraft">
            <fieldset class="m-0 min-w-0 border-0 p-0">
                <legend class="mb-3 text-sm font-semibold text-(--app-color-text)">Estado</legend>
                <div class="grid grid-cols-2 gap-2">
                    <label
                        v-for="status in props.statusOptions"
                        :key="status"
                        class="filter-option"
                    >
                        <input v-model="draft.statuses" type="checkbox" :value="status" />
                        <span>{{ status === 'Activo' ? 'Activas' : 'Inactivas' }}</span>
                    </label>
                </div>
            </fieldset>
            <fieldset class="m-0 min-w-0 border-0 p-0">
                <legend class="mb-3 text-sm font-semibold text-(--app-color-text)">
                    Tipo de cuenta
                </legend>
                <div class="grid grid-cols-2 gap-2">
                    <label
                        v-for="kind in props.kindOptions"
                        :key="kind.value"
                        class="filter-option"
                    >
                        <input v-model="draft.kinds" type="checkbox" :value="kind.value" />
                        <span>{{ kind.label }}</span>
                    </label>
                </div>
            </fieldset>
            <fieldset class="m-0 min-w-0 border-0 p-0">
                <legend class="mb-3 text-sm font-semibold text-(--app-color-text)">Formato</legend>
                <div class="grid grid-cols-2 gap-2">
                    <label
                        v-for="surface in props.surfaceOptions"
                        :key="surface.value"
                        class="filter-option"
                    >
                        <input v-model="draft.surfaces" type="checkbox" :value="surface.value" />
                        <span>{{ surface.label }}</span>
                    </label>
                </div>
            </fieldset>
        </form>
        <template #footer>
            <div
                class="flex w-full items-center justify-between gap-3 pb-[env(safe-area-inset-bottom)]"
            >
                <AppButton variant="ghost" @click="clearDraft">Limpiar filtros</AppButton>
                <AppButton type="submit" form="account-filters-form">Aplicar filtros</AppButton>
            </div>
        </template>
    </AppModal>
</template>

<style scoped>
@reference "../../../main.css";
.filter-option {
    @apply flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-(--app-color-border) bg-(--app-color-surface) px-3 py-3 text-sm font-medium text-(--app-color-text) transition;
}
.filter-option:has(input:checked) {
    border-color: var(--app-color-primary);
    background: color-mix(in srgb, var(--app-color-primary) 8%, var(--app-color-surface));
}
.filter-option:focus-within {
    outline: 2px solid var(--app-color-primary);
    outline-offset: 2px;
}
.filter-option input {
    width: 1.125rem;
    height: 1.125rem;
    flex-shrink: 0;
    accent-color: var(--app-color-primary);
}
</style>
