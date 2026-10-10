<script setup lang="ts">
import { AppActionMenu, AppCard } from '@/modules/shared/components';

type UserAccessStatus = 'active' | 'invited';

const props = defineProps<{
    canManage?: boolean;
    itemId: string;
    name: string;
    email: string;
    roleLabel: string;
    accessLabel: string;
    allocationPercentage: number;
    custodyAmount: number;
    settlementAmount: number;
    status: UserAccessStatus;
}>();

const emit = defineEmits<{
    edit: [itemId: string];
    delete: [itemId: string];
}>();

function statusLabel(status: UserAccessStatus): string {
    if (status === 'invited') {
        return 'Invitado';
    }

    return 'Activo';
}

function statusClasses(status: UserAccessStatus): string {
    if (status === 'invited') {
        return 'bg-[color-mix(in_srgb,var(--app-color-warning)_12%,transparent)] text-(--app-color-warning)';
    }

    return 'bg-[color-mix(in_srgb,var(--app-color-success)_10%,transparent)] text-(--app-color-success)';
}

function accentStyle(status: UserAccessStatus): string {
    if (status === 'invited') {
        return 'color-mix(in srgb, #f59e0b 16%, transparent)';
    }

    return 'color-mix(in srgb, var(--app-color-primary) 12%, transparent)';
}

function handleEdit(): void {
    emit('edit', props.itemId);
}

function handleDelete(): void {
    emit('delete', props.itemId);
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function settlementLabel(value: number): string {
    if (value > 0) {
        return `Por recibir ${formatCurrency(value)}`;
    }

    if (value < 0) {
        return `Por pagar ${formatCurrency(Math.abs(value))}`;
    }

    return 'Sin deuda';
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
                        {{ props.name }}
                    </p>
                    <p class="text-sm leading-5 break-words text-(--app-color-text-muted)">
                        {{ props.email }}
                    </p>
                </div>

                <div class="space-y-1 text-right">
                    <p
                        class="text-sm font-semibold tracking-tight text-(--app-color-text) tabular-nums"
                    >
                        {{ props.allocationPercentage }}%
                    </p>
                    <p
                        class="text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
                    >
                        Participación
                    </p>
                </div>

                <AppActionMenu
                    v-if="props.canManage !== false"
                    class="shrink-0"
                    @delete="handleDelete"
                    @edit="handleEdit"
                />
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <span
                        class="inline-flex shrink-0 items-center rounded-(--app-radius-control) px-2 py-0.5 text-[10px] font-semibold tracking-[0.04em] uppercase"
                        :class="statusClasses(props.status)"
                    >
                        {{ statusLabel(props.status) }}
                    </span>
                    <p
                        class="text-[11px] font-medium tracking-[0.04em] break-words text-(--app-color-text-subtle) uppercase"
                    >
                        {{ props.roleLabel }} · {{ props.accessLabel }}
                    </p>
                </div>

                <div class="text-left sm:shrink-0 sm:text-right">
                    <p
                        class="text-[11px] font-semibold tracking-[0.04em] text-(--app-color-text) uppercase"
                    >
                        {{ settlementLabel(props.settlementAmount) }}
                    </p>
                    <p class="text-[11px] font-medium text-(--app-color-text-subtle)">
                        Custodia {{ formatCurrency(props.custodyAmount) }}
                    </p>
                </div>
            </div>
        </div>
    </AppCard>
</template>
