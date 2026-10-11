<script setup lang="ts">
import { computed } from 'vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

import { AppActionMenu, AppCard } from '@/modules/shared/components';

type TransactionItemType = 'income' | 'expense';

const props = defineProps<{
    itemId: string;
    concept: string;
    categoryName?: string | null;
    amount: number;
    type: TransactionItemType;
    dateLabel: string;
    creatorName: string | null;
    accountName?: string | null;
    metaLabel?: string | null;
    showActions?: boolean;
    pendingReimbursementAmount?: number;
    receivableReimbursementAmount?: number;
}>();

const emit = defineEmits<{
    edit: [itemId: string];
    delete: [itemId: string];
}>();

const currencyFormatter = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const actionMenuItems = computed(() => {
    return [
        {
            key: 'edit',
            label: 'Editar',
            icon: PencilSquareIcon,
            tone: 'default' as const,
        },
        {
            key: 'delete',
            label: 'Eliminar',
            icon: TrashIcon,
            tone: 'danger' as const,
        },
    ];
});

function formattedAmount(amount: number): string {
    return currencyFormatter.format(amount);
}

function amountClasses(type: TransactionItemType): string {
    if (type === 'income') {
        return 'text-(--app-color-success)';
    }

    return 'text-(--app-color-danger)';
}

function signLabel(type: TransactionItemType): string {
    if (type === 'income') {
        return '+';
    }

    return '−';
}

function creatorLabel(): string {
    return props.creatorName?.trim() || 'Usuario no disponible';
}

function secondaryLabel(): string {
    const metaLabel = props.metaLabel?.trim();

    if (metaLabel) {
        return metaLabel;
    }

    const accountName = props.accountName?.trim();

    if (accountName) {
        return `${accountName} · ${creatorLabel()}`;
    }

    return creatorLabel();
}

function hasPendingReimbursement(): boolean {
    return Boolean(props.pendingReimbursementAmount && props.pendingReimbursementAmount > 0);
}

function hasReceivableReimbursement(): boolean {
    return Boolean(props.receivableReimbursementAmount && props.receivableReimbursementAmount > 0);
}

function handleEdit(): void {
    emit('edit', props.itemId);
}

function handleDelete(): void {
    emit('delete', props.itemId);
}
</script>

<template>
    <AppCard
        class="relative overflow-hidden rounded-(--app-radius-control) px-4! py-3! shadow-none transition hover:border-(--app-color-border-strong)"
    >
        <div
            class="pointer-events-none absolute inset-y-0 left-0 w-1"
            :style="{
                background:
                    type === 'income'
                        ? 'color-mix(in srgb, #10b981 14%, transparent)'
                        : 'color-mix(in srgb, var(--app-color-primary) 10%, transparent)',
            }"
        />

        <div
            class="relative grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-1 sm:grid-cols-[minmax(0,1fr)_auto_auto] lg:grid-cols-[minmax(0,1fr)_7rem_minmax(8rem,auto)_2.75rem] lg:items-center lg:gap-x-4"
        >
            <div class="min-w-0">
                <p class="text-sm leading-5 font-semibold break-words text-(--app-color-text)">
                    {{ props.concept }}
                </p>
                <p class="mt-1 text-xs wrap-anywhere text-(--app-color-text-subtle)">
                    {{ props.categoryName || 'Sin categoría' }}
                </p>
            </div>

            <div
                class="col-start-1 row-start-2 flex min-w-0 flex-col items-start gap-1.5 sm:col-span-1 sm:col-start-2 sm:row-start-1 sm:items-end lg:col-start-3 lg:row-span-2"
            >
                <p
                    class="shrink-0 text-sm font-semibold tracking-tight whitespace-nowrap tabular-nums sm:text-base"
                    :class="amountClasses(props.type)"
                >
                    <span class="mr-1">{{ signLabel(props.type) }}</span
                    >{{ formattedAmount(props.amount) }}
                </p>

                <div
                    v-if="hasPendingReimbursement() || hasReceivableReimbursement()"
                    class="flex max-w-full flex-col items-start gap-1 sm:items-end"
                >
                    <p
                        v-if="hasPendingReimbursement()"
                        class="inline-flex rounded-(--app-radius-control) bg-[color-mix(in_srgb,var(--app-color-warning)_14%,transparent)] px-2 py-0.5 text-[11px] leading-4 font-semibold break-words text-(--app-color-warning)"
                    >
                        Por pagar {{ formattedAmount(props.pendingReimbursementAmount ?? 0) }}
                    </p>

                    <p
                        v-if="hasReceivableReimbursement()"
                        class="inline-flex rounded-(--app-radius-control) bg-[color-mix(in_srgb,var(--app-color-success)_14%,transparent)] px-2 py-0.5 text-[11px] leading-4 font-semibold break-words text-(--app-color-success)"
                    >
                        Por recibir {{ formattedAmount(props.receivableReimbursementAmount ?? 0) }}
                    </p>
                </div>
            </div>

            <AppActionMenu
                v-if="props.showActions"
                class="col-start-2 row-span-2 row-start-1 shrink-0 sm:col-start-3 lg:col-start-4"
                :actions="actionMenuItems"
                @delete="handleDelete"
                @edit="handleEdit"
            />
            <div
                v-else
                class="col-start-2 row-span-2 row-start-1 w-11 shrink-0 sm:col-start-3 sm:h-6 sm:w-7 lg:col-start-4"
                aria-hidden="true"
            />

            <p
                class="col-start-1 row-start-3 min-w-0 text-[11px] font-medium break-words text-(--app-color-text-subtle) sm:row-start-2"
            >
                {{ secondaryLabel() }}
            </p>

            <p
                class="col-start-2 row-start-3 justify-self-end text-[11px] font-medium tracking-[0.04em] whitespace-nowrap text-(--app-color-text-subtle) uppercase sm:col-start-2 sm:row-start-2 sm:justify-self-end lg:row-span-2 lg:row-start-1 lg:self-center"
            >
                {{ props.dateLabel }}
            </p>
        </div>
    </AppCard>
</template>
