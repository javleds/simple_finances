<script setup lang="ts">
import { AppActionMenu, AppCard } from '@/modules/shared/components';

type InvitationStatus = 'pending' | 'resent' | 'accepted';

const props = defineProps<{
  itemId: string;
  name: string;
  email: string;
  roleLabel: string;
  metaLabel: string;
  status: InvitationStatus;
}>();

const emit = defineEmits<{
  edit: [itemId: string];
  delete: [itemId: string];
}>();

function statusLabel(status: InvitationStatus): string {
  if (status === 'accepted') {
    return 'Aceptada';
  }

  if (status === 'resent') {
    return 'Reenviada';
  }

  return 'Pendiente';
}

function statusClasses(status: InvitationStatus): string {
  if (status === 'accepted') {
    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
  }

  if (status === 'resent') {
    return 'bg-sky-500/10 text-sky-700 dark:text-sky-300';
  }

  return 'bg-amber-500/12 text-amber-700 dark:text-amber-300';
}

function accentStyle(status: InvitationStatus): string {
  if (status === 'accepted') {
    return 'linear-gradient(90deg, color-mix(in srgb, #10b981 14%, transparent), transparent 78%)';
  }

  if (status === 'resent') {
    return 'linear-gradient(90deg, color-mix(in srgb, #0ea5e9 14%, transparent), transparent 78%)';
  }

  return 'linear-gradient(90deg, color-mix(in srgb, #f59e0b 16%, transparent), transparent 78%)';
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
    class="relative overflow-hidden rounded-xl !p-3.5 shadow-none transition hover:border-[var(--app-color-border-strong)]"
  >
    <div
      class="pointer-events-none absolute inset-y-0 left-0 w-24 opacity-90"
      :style="{ background: accentStyle(props.status) }"
    />

    <div class="relative space-y-3">
      <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-start gap-3">
        <div class="min-w-0 space-y-1">
          <p
            class="overflow-hidden text-sm font-semibold leading-5 text-[var(--app-color-text)] [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
          >
            {{ props.name }}
          </p>
          <p class="truncate text-sm leading-5 text-[var(--app-color-text-muted)]">
            {{ props.email }}
          </p>
        </div>

        <div class="space-y-1 text-right">
          <p class="text-sm font-semibold text-[var(--app-color-text)]">
            {{ props.roleLabel }}
          </p>
          <p class="text-[11px] font-medium uppercase tracking-[0.04em] text-[var(--app-color-text-subtle)]">
            Rol invitado
          </p>
        </div>

        <AppActionMenu class="shrink-0" @delete="handleDelete" @edit="handleEdit" />
      </div>

      <div class="flex items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
          <span
            class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.04em]"
            :class="statusClasses(props.status)"
          >
            {{ statusLabel(props.status) }}
          </span>
          <p class="truncate text-[11px] font-medium uppercase tracking-[0.04em] text-[var(--app-color-text-subtle)]">
            {{ props.metaLabel }}
          </p>
        </div>
      </div>
    </div>
  </AppCard>
</template>
