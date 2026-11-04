<script setup lang="ts">
import { validateCategorySelection } from '@/modules/transactions/lib/transactionAdditionalFilters';
import TransactionAdditionalFilters from '@/modules/transactions/components/TransactionAdditionalFilters.vue';
import { computed, ref, watch } from 'vue';
import type { TransactionType } from '@/modules/transactions/types';
import { AppFilterPanel, AppFilterOptions, AppText } from '@/modules/shared/components';

const props = withDefaults(
    defineProps<{
        open: boolean;
        accountId?: string;
        selectedCategories?: string[];
        hasAttachments?: boolean | null;
        selectedTypes: TransactionType[];
        typeOptions: readonly { value: TransactionType; label: string }[];
    }>(),
    { hasAttachments: null, selectedCategories: () => [] },
);

const emit = defineEmits<{
    apply: [values: TransactionType[]];
    applyAdditional: [values: { categories: string[]; hasAttachments: boolean | null }];
    close: [];
}>();

const draft = ref<TransactionType[]>([]);
const draftCategories = ref<string[]>([]);
const draftAttachments = ref<boolean | null>(null);
const categoryError = computed(() => validateCategorySelection(draftCategories.value));

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        draft.value = [...props.selectedTypes];
        draftCategories.value = [...(props.selectedCategories ?? [])];
        draftAttachments.value = props.hasAttachments ?? null;
    },
    { immediate: true },
);

function apply(): void {
    if (categoryError.value) return;
    emit('apply', [...draft.value]);
    emit('applyAdditional', {
        categories: [...draftCategories.value],
        hasAttachments: draftAttachments.value,
    });
    emit('close');
}
</script>

<template>
    <AppFilterPanel
        id="account-transaction-filters"
        :open="props.open"
        :apply-disabled="Boolean(categoryError)"
        @apply="apply"
        @clear="
            draft = [];
            draftCategories = [];
            draftAttachments = null;
        "
        @close="emit('close')"
    >
        <div class="grid gap-6">
            <AppFilterOptions v-model="draft" label="Tipo" :options="props.typeOptions" />
            <AppText v-if="categoryError" size="sm" role="alert">{{ categoryError }}</AppText>
            <TransactionAdditionalFilters
                v-model:categories="draftCategories"
                v-model:has-attachments="draftAttachments"
                :account-id="props.accountId"
                account-scoped
            />
        </div>
    </AppFilterPanel>
</template>
