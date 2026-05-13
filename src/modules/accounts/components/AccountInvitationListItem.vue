<script setup lang="ts">
import { AppActionMenu, AppCard } from '@/modules/shared/components';

type InvitationStatus = 'pending' | 'accepted' | 'declined';

const props = defineProps<{
  itemId: string;
  email: string;
  percentageLabel: string;
  metaLabel: string;
  status: InvitationStatus;
}>();

const emit = defineEmits<{
  delete: [itemId: string];
}>();

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
    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
  }

  if (status === 'declined') {
    return 'bg-slate-500/10 text-slate-600 dark:text-slate-300';
  }

  return 'bg-amber-500/12 text-amber-700 dark:text-amber-300';
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
      <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-start gap-3">
        <div class="min-w-0 space-y-1">
          <p
            class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
          >
            {{ props.email }}
          </p>
          <p class="truncate text-sm leading-5 text-(--app-color-text-muted)">
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

        <AppActionMenu class="shrink-0" @delete="handleDelete" />
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
