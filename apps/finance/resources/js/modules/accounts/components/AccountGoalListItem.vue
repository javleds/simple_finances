<script setup lang="ts">
import { AppActionMenu, AppCard } from '@/modules/shared/components';

type GoalStatus = 'on-track' | 'at-risk' | 'completed';

const props = defineProps<{
    itemId: string;
    title: string;
    ownerLabel: string;
    achievedAmount: number;
    targetAmount: number;
    progress: number;
    status: GoalStatus;
    deadlineLabel: string;
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

function accentStyle(status: GoalStatus): string {
    if (status === 'completed') {
        return 'color-mix(in srgb, #10b981 16%, transparent)';
    }

    if (status === 'at-risk') {
        return 'color-mix(in srgb, #f59e0b 16%, transparent)';
    }

    return 'color-mix(in srgb, var(--app-color-primary) 12%, transparent)';
}

function progressBarStyle(status: GoalStatus): string {
    if (status === 'completed') {
        return '#10b981';
    }

    if (status === 'at-risk') {
        return '#f59e0b';
    }

    return 'var(--app-color-primary)';
}

function formattedAmount(amount: number): string {
    return currencyFormatter.format(amount);
}

function formattedProgress(progress: number): string {
    return `${progress.toFixed(2)}%`;
}

function progressWidth(progress: number): string {
    return `${Math.min(Math.max(progress, 0), 100)}%`;
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
        class="relative overflow-hidden rounded-(--app-radius-control) p-4! shadow-none transition hover:border-(--app-color-border-strong)"
    >
        <div
            class="pointer-events-none absolute inset-y-0 left-0 w-1"
            :style="{ background: accentStyle(props.status) }"
        />

        <div class="relative space-y-3">
            <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-start gap-3">
                <div class="min-w-0 space-y-1">
                    <p class="text-sm leading-5 font-semibold break-words text-(--app-color-text)">
                        {{ props.title }}
                    </p>
                </div>

                <div class="space-y-1 text-right">
                    <p
                        class="text-sm font-semibold tracking-tight text-(--app-color-text) tabular-nums"
                    >
                        {{ formattedAmount(props.targetAmount) }}
                    </p>
                </div>

                <AppActionMenu class="shrink-0" @delete="handleDelete" @edit="handleEdit" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <p
                        class="truncate text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
                    >
                        {{ props.deadlineLabel }}
                    </p>
                </div>

                <p
                    class="shrink-0 text-[11px] font-semibold tracking-[0.04em] text-(--app-color-text) uppercase"
                >
                    {{ formattedProgress(props.progress) }}
                </p>
            </div>

            <div class="relative h-7 overflow-hidden rounded-(--app-radius-control) bg-(--app-color-surface-muted)">
                <div
                    class="h-full rounded-(--app-radius-control)"
                    :style="{
                        width: progressWidth(props.progress),
                        backgroundColor: progressBarStyle(props.status),
                    }"
                />
                <p
                    class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-(--app-color-text) tabular-nums"
                >
                    {{ formattedAmount(props.achievedAmount) }}
                </p>
            </div>
        </div>
    </AppCard>
</template>
