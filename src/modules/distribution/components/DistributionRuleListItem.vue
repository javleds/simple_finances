<script setup lang="ts">
import { useRouter } from 'vue-router';

import { AppActionMenu, AppCard } from '@/modules/shared/components';

type DistributionStatus = 'active' | 'draft' | 'paused';

const props = defineProps<{
  itemId: string;
  name: string;
  description: string;
  allocation: number;
  destination: string;
  status: DistributionStatus;
}>();

const emit = defineEmits<{
  edit: [itemId: string];
  delete: [itemId: string];
}>();

const router = useRouter();

function statusLabel(status: DistributionStatus): string {
  if (status === 'paused') {
    return 'Pausada';
  }

  if (status === 'draft') {
    return 'Borrador';
  }

  return 'Activa';
}

function statusClasses(status: DistributionStatus): string {
  if (status === 'paused') {
    return 'bg-slate-500/10 text-slate-600 dark:text-slate-300';
  }

  if (status === 'draft') {
    return 'bg-amber-500/12 text-amber-700 dark:text-amber-300';
  }

  return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
}

function accentStyle(status: DistributionStatus): string {
  if (status === 'paused') {
    return 'linear-gradient(90deg, color-mix(in srgb, #64748b 14%, transparent), transparent 78%)';
  }

  if (status === 'draft') {
    return 'linear-gradient(90deg, color-mix(in srgb, #f59e0b 16%, transparent), transparent 78%)';
  }

  return 'linear-gradient(90deg, color-mix(in srgb, var(--app-color-primary) 12%, transparent), transparent 78%)';
}

function handleEdit(): void {
  emit('edit', props.itemId);
}

function handleDelete(): void {
  emit('delete', props.itemId);
}

function openRuleDetails(): void {
  router.push({
    name: 'admin.distribution.detail',
    params: { ruleId: props.itemId },
  });
}
</script>

<template>
  <AppCard
    class="relative cursor-pointer overflow-hidden rounded-xl p-3.5! shadow-none transition hover:border-(--app-color-border-strong)"
    role="link"
    tabindex="0"
    @click="openRuleDetails"
    @keydown.enter="openRuleDetails"
    @keydown.space.prevent="openRuleDetails"
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
            {{ props.name }}
          </p>
          <p class="text-sm leading-5 text-(--app-color-text-muted)">
            {{ props.description }}
          </p>
        </div>

        <div class="space-y-1 text-right">
          <p class="text-sm font-semibold tracking-tight text-(--app-color-text) tabular-nums">
            {{ props.allocation }}%
          </p>
          <p
            class="text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
          >
            Asignación
          </p>
        </div>

        <AppActionMenu
          class="shrink-0"
          @delete.prevent.stop="handleDelete"
          @edit.prevent.stop="handleEdit"
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
            {{ props.destination }}
          </p>
        </div>
      </div>
    </div>
  </AppCard>
</template>
