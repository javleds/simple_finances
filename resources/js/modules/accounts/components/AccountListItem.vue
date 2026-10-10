<script setup lang="ts">
import Button from 'primevue/button';
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import {
    ArrowRightStartOnRectangleIcon,
    PencilSquareIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

import { canDeleteAccount, canLeaveAccount } from '@/modules/accounts/lib/accountPermissions';
import type { Account } from '@/modules/accounts/types';
import { AppActionMenu, AppCard } from '@/modules/shared/components';

const props = defineProps<{
    account: Account;
    currentUserId: string | null;
}>();

const emit = defineEmits<{
    edit: [accountId: string];
    delete: [accountId: string];
    leave: [accountId: string];
}>();

const router = useRouter();

const accountActions = computed(() => {
    if (canDeleteAccount(props.account, props.currentUserId)) {
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
    }

    if (canLeaveAccount(props.account, props.currentUserId)) {
        return [
            {
                key: 'leave',
                label: 'Salir de la cuenta',
                icon: ArrowRightStartOnRectangleIcon,
                tone: 'danger' as const,
            },
        ];
    }

    return [];
});

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function balanceClasses(amount: Account['balance']): string {
    if (amount < 0) {
        return 'text-(--app-color-danger)';
    }

    return 'text-(--app-color-text-muted)';
}

function statusClasses(status: Account['status']): string {
    if (status === 'Activo') {
        return 'bg-[color-mix(in_srgb,var(--app-color-success)_10%,transparent)] text-(--app-color-success)';
    }

    return 'bg-(--app-color-surface-muted) text-(--app-color-text-muted)';
}

function handleEdit(): void {
    emit('edit', props.account.id);
}

function handleDelete(): void {
    emit('delete', props.account.id);
}

function handleLeave(): void {
    emit('leave', props.account.id);
}

function handleAction(actionKey: string): void {
    if (actionKey === 'leave') {
        handleLeave();
    }
}

function openAccountDetails(): void {
    router.push({
        name: 'admin.accounts.transactions',
        params: { accountId: props.account.id },
    });
}
</script>

<template>
    <AppCard
        class="relative overflow-hidden rounded-xl p-4! shadow-none transition hover:border-(--app-color-border-strong)"
    >
        <div
            class="pointer-events-none absolute inset-y-0 left-0 w-1"
            :style="{
                background: `color-mix(in srgb, ${props.account.color ?? '#94A3B8'} 14%, transparent)`,
            }"
        />

        <div class="relative grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-2">
            <Button
                variant="text"
                severity="secondary"
                type="button"
                class="col-start-1 row-start-1 grid min-w-0 cursor-pointer grid-cols-1 gap-x-3 gap-y-2 rounded-xl p-0! text-left focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none sm:grid-cols-[minmax(0,1fr)_auto]"
                @click="openAccountDetails"
                @keydown.enter.prevent="openAccountDetails"
                @keydown.space.prevent="openAccountDetails"
            >
                <div class="min-w-0">
                    <p class="text-sm leading-5 font-semibold break-words text-(--app-color-text)">
                        {{ props.account.name }}
                    </p>
                </div>

                <p
                    class="shrink-0 text-sm font-semibold tracking-tight whitespace-nowrap text-(--app-color-text) tabular-nums sm:text-base"
                    :class="balanceClasses(props.account.balance)"
                >
                    {{ formatCurrency(props.account.balance) }}
                </p>

                <div class="flex min-w-0 flex-wrap items-center gap-2 sm:col-span-2">
                    <p
                        class="truncate text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
                    >
                        {{ props.account.isVirtual ? 'Virtual' : 'Fisica' }}
                    </p>
                    <span
                        class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-[0.04em] uppercase"
                        :class="statusClasses(props.account.status)"
                    >
                        {{ props.account.status }}
                    </span>
                </div>
            </Button>
            <AppActionMenu
                v-if="accountActions.length > 0"
                class="col-start-2 row-start-1 shrink-0"
                :actions="accountActions"
                @action="handleAction"
                @delete="handleDelete"
                @edit="handleEdit"
            />
        </div>
    </AppCard>
</template>
