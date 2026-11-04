<script setup lang="ts">
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline';

import type { AccountInviteStatus } from '@/modules/accounts/schemas/accountInviteSchemas';

import { AppActionMenu, AppCard } from '@/modules/shared/components';

const props = defineProps<{
    itemId: string;
    accountName: string;
    invitedBy: string;
    status: AccountInviteStatus;
    metaLabel: string;
}>();

const emit = defineEmits<{
    accept: [itemId: string];
    reject: [itemId: string];
}>();

const menuActions = [
    {
        key: 'accept',
        label: 'Aceptar',
        icon: CheckCircleIcon,
        tone: 'success',
    },
    {
        key: 'reject',
        label: 'Rechazar',
        icon: XCircleIcon,
        tone: 'danger',
    },
] as const;

function statusLabel(status: AccountInviteStatus): string {
    if (status === 'accepted') {
        return 'Aceptada';
    }

    if (status === 'declined') {
        return 'Rechazada';
    }

    return 'Pendiente';
}

function statusClasses(status: AccountInviteStatus): string {
    if (status === 'accepted') {
        return 'bg-[color-mix(in_srgb,var(--app-color-success)_12%,transparent)] text-(--app-color-success)';
    }

    if (status === 'declined') {
        return 'bg-[color-mix(in_srgb,var(--app-color-danger)_12%,transparent)] text-(--app-color-danger)';
    }

    return 'bg-[color-mix(in_srgb,var(--app-color-primary)_10%,transparent)] text-(--app-color-primary)';
}

function handleAction(actionKey: string): void {
    if (props.status !== 'pending') {
        return;
    }

    if (actionKey === 'accept') {
        emit('accept', props.itemId);
        return;
    }

    if (actionKey === 'reject') {
        emit('reject', props.itemId);
    }
}
</script>

<template>
    <AppCard class="p-4! transition hover:border-(--app-color-border-strong)">
        <div
            class="relative space-y-3 lg:grid lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-center lg:gap-6 lg:space-y-0"
        >
            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                <div class="min-w-0 space-y-1">
                    <p
                        class="text-base leading-6 font-semibold wrap-anywhere text-(--app-color-text)"
                    >
                        {{ props.accountName }}
                    </p>
                    <p class="text-sm leading-5 wrap-anywhere text-(--app-color-text-muted)">
                        Invitó {{ props.invitedBy }}
                    </p>
                </div>

                <AppActionMenu
                    v-if="props.status === 'pending'"
                    :actions="menuActions"
                    class="shrink-0"
                    @action="handleAction"
                />
            </div>

            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <span
                        class="inline-flex shrink-0 items-center rounded-(--app-radius-control) px-2 py-0.5 text-xs font-semibold tracking-[0.04em] uppercase"
                        :class="statusClasses(props.status)"
                    >
                        {{ statusLabel(props.status) }}
                    </span>
                    <p class="text-xs wrap-anywhere text-(--app-color-text-subtle)">
                        {{ props.metaLabel }}
                    </p>
                </div>
            </div>
        </div>
    </AppCard>
</template>
