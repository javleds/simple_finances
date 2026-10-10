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
    return 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300';
  }

  if (status === 'declined') {
    return 'bg-red-500/12 text-red-700 dark:text-red-300';
  }

  return 'bg-sky-500/10 text-sky-700 dark:text-sky-300';
}

function accentStyle(status: AccountInviteStatus): string {
  if (status === 'declined') {
    return 'linear-gradient(90deg, color-mix(in srgb, #f59e0b 16%, transparent), transparent 78%)';
  }

  return 'linear-gradient(90deg, color-mix(in srgb, var(--app-color-primary) 12%, transparent), transparent 78%)';
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
  <AppCard
    class="relative overflow-hidden rounded-xl p-3.5! shadow-none transition hover:border-(--app-color-border-strong)"
  >
    <div
      class="pointer-events-none absolute inset-y-0 left-0 w-24 opacity-90"
      :style="{ background: accentStyle(props.status) }"
    />

    <div class="relative space-y-3">
      <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
        <div class="min-w-0 space-y-1">
          <p
            class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
          >
            {{ props.accountName }}
          </p>
          <p class="truncate text-sm leading-5 text-(--app-color-text-muted)">
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
        <div class="flex min-w-0 items-center gap-2">
          <span
            class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-[0.04em] uppercase"
            :class="statusClasses(props.status)"
          >
            {{ statusLabel(props.status) }}
          </span>
          <p
            class="truncate text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
          >
            {{ props.metaLabel }}
          </p>
        </div>
      </div>
    </div>
  </AppCard>
</template>
