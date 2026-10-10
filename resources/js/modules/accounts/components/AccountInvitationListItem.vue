<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';
import { ArrowPathIcon, TrashIcon } from '@heroicons/vue/24/outline';

import { AppActionMenu, AppCard } from '@/modules/shared/components';

type InvitationStatus = 'pending' | 'accepted' | 'declined';

const props = defineProps<{
    canManage?: boolean;
    itemId: string;
    email: string;
    percentageLabel: string;
    metaLabel: string;
    status: InvitationStatus;
}>();

const emit = defineEmits<{
    delete: [itemId: string];
    edit: [itemId: string];
    resend: [itemId: string];
}>();

const canManageInvitation = computed(
    () => props.canManage !== false && props.status !== 'accepted',
);
const declinedActions: ReadonlyArray<ActionMenuItem> = [
    {
        key: 'resend',
        label: 'Reenviar',
        icon: ArrowPathIcon,
        tone: 'success',
    },
    {
        key: 'delete',
        label: 'Eliminar',
        icon: TrashIcon,
        tone: 'danger',
    },
] as const;
const menuActions = computed(() => (props.status === 'declined' ? declinedActions : []));

type ActionMenuItem = {
    key: string;
    label: string;
    icon: Component;
    tone?: 'default' | 'danger' | 'success';
};

function statusLabel(status: InvitationStatus): string {
    if (status === 'accepted') {
        return 'Aceptada';
    }

    if (status === 'declined') {
        return 'Declinada';
    }

    return 'Pendiente';
}

function statusClasses(status: InvitationStatus): string {
    if (status === 'accepted') {
        return 'bg-[color-mix(in_srgb,var(--app-color-success)_10%,transparent)] text-(--app-color-success)';
    }

    if (status === 'declined') {
        return 'bg-(--app-color-surface-muted) text-(--app-color-text-muted)';
    }

    return 'bg-[color-mix(in_srgb,var(--app-color-warning)_12%,transparent)] text-(--app-color-warning)';
}

function accentStyle(status: InvitationStatus): string {
    if (status === 'accepted') {
        return 'linear-gradient(90deg, color-mix(in srgb, #10b981 14%, transparent), transparent 78%)';
    }

    if (status === 'declined') {
        return 'linear-gradient(90deg, color-mix(in srgb, #64748b 14%, transparent), transparent 78%)';
    }

    return 'linear-gradient(90deg, color-mix(in srgb, #f59e0b 16%, transparent), transparent 78%)';
}

function handleDelete(): void {
    emit('delete', props.itemId);
}

function handleEdit(): void {
    emit('edit', props.itemId);
}

function handleAction(actionKey: string): void {
    if (actionKey === 'resend') {
        emit('resend', props.itemId);
    }
}
</script>

<template>
    <AppCard
        class="relative overflow-hidden rounded-(--app-radius-control) p-4! shadow-none transition hover:border-(--app-color-border-strong)"
    >
        <div
            class="pointer-events-none absolute inset-y-0 left-0 w-24 opacity-90"
            :style="{ background: accentStyle(props.status) }"
        />

        <div
            class="relative space-y-3 lg:grid lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-center lg:gap-6 lg:space-y-0"
        >
            <div
                class="grid items-start gap-3"
                :class="
                    canManageInvitation
                        ? 'grid-cols-[minmax(0,1fr)_auto_auto]'
                        : 'grid-cols-[minmax(0,1fr)_auto]'
                "
            >
                <div class="min-w-0 space-y-1">
                    <p class="text-sm leading-5 font-semibold break-words text-(--app-color-text)">
                        {{ props.email }}
                    </p>
                    <p class="text-sm leading-5 break-words text-(--app-color-text-muted)">
                        Invitación de cuenta compartida
                    </p>
                </div>

                <div class="space-y-1 text-right">
                    <p class="text-sm font-semibold text-(--app-color-text)">
                        {{ props.percentageLabel }}
                    </p>
                    <p
                        class="text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
                    >
                        reparto asignado
                    </p>
                </div>

                <AppActionMenu
                    v-if="canManageInvitation"
                    :actions="menuActions"
                    class="shrink-0"
                    @action="handleAction"
                    @delete="handleDelete"
                    @edit="handleEdit"
                />
            </div>

            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <span
                        class="inline-flex shrink-0 items-center rounded-(--app-radius-control) px-2 py-0.5 text-[10px] font-semibold tracking-[0.04em] uppercase"
                        :class="statusClasses(props.status)"
                    >
                        {{ statusLabel(props.status) }}
                    </span>
                    <p
                        class="text-[11px] font-medium tracking-[0.04em] break-words text-(--app-color-text-subtle) uppercase"
                    >
                        {{ props.metaLabel }}
                    </p>
                </div>
            </div>
        </div>
    </AppCard>
</template>
