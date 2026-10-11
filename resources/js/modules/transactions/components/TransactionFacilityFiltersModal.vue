<script setup lang="ts">
import { validateCategorySelection } from '@/modules/transactions/lib/transactionAdditionalFilters';
import TransactionAdditionalFilters from './TransactionAdditionalFilters.vue';
import { computed, ref, watch } from 'vue';
import {
    currentMonthRange,
    validateTransactionPeriod,
    type TransactionPeriod,
} from '@/modules/transactions/lib/transactionPeriod';
import { AppDatePicker, AppFilterPanel, AppText } from '@/modules/shared/components';

const props = withDefaults(
    defineProps<{
        open: boolean;
        startDate: string | null;
        endDate: string | null;
        selectedCategories?: string[];
        hasAttachments?: boolean | null;
    }>(),
    { hasAttachments: null, selectedCategories: () => [] },
);
const emit = defineEmits<{
    apply: [period: TransactionPeriod & { categories: string[]; hasAttachments: boolean | null }];
    close: [];
}>();
const draftStart = ref<string | null>(null);
const draftEnd = ref<string | null>(null);
const draftCategories = ref<string[]>([]);
const draftAttachments = ref<boolean | null>(null);
const categoryError = computed(() => validateCategorySelection(draftCategories.value));
const validationError = computed(() => validateTransactionPeriod(draftStart.value, draftEnd.value));

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        draftStart.value = props.startDate;
        draftEnd.value = props.endDate;
        draftCategories.value = [...(props.selectedCategories ?? [])];
        draftAttachments.value = props.hasAttachments ?? null;
    },
    { immediate: true },
);

function resetDraft(): void {
    const range = currentMonthRange();
    draftStart.value = range.startDate;
    draftEnd.value = range.endDate;
    draftCategories.value = [];
    draftAttachments.value = null;
}

function applyDraft(): void {
    if (categoryError.value || validationError.value || !draftStart.value || !draftEnd.value)
        return;
    emit('apply', {
        startDate: draftStart.value,
        endDate: draftEnd.value,
        categories: [...draftCategories.value],
        hasAttachments: draftAttachments.value,
    });
    emit('close');
}
</script>

<template>
    <AppFilterPanel
        id="transaction-period-filters"
        :open="props.open"
        :apply-disabled="Boolean(validationError || categoryError)"
        clear-label="Limpiar"
        @apply="applyDraft"
        @clear="resetDraft"
        @close="emit('close')"
    >
        <div class="grid gap-6">
            <fieldset class="m-0 min-w-0 border-0 p-0">
                <legend class="mb-3 text-sm font-semibold text-(--app-color-text)">Periodo</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <AppDatePicker
                        id="transaction-facility-start-date"
                        v-model="draftStart"
                        label="Desde"
                        :clearable="false"
                    />
                    <AppDatePicker
                        id="transaction-facility-end-date"
                        v-model="draftEnd"
                        label="Hasta"
                        :clearable="false"
                        :error="validationError ?? undefined"
                    />
                </div>
            </fieldset>
            <AppText v-if="categoryError" size="sm" role="alert">{{ categoryError }}</AppText>
            <TransactionAdditionalFilters
                v-model:categories="draftCategories"
                v-model:has-attachments="draftAttachments"
            />
        </div>
    </AppFilterPanel>
</template>
