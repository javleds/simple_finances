<script setup lang="ts">
import { ChevronRightIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';

import type { AccountRecord } from '@/modules/accounts/data/accounts';
import { AppActionMenu, AppCard } from '@/modules/shared/components';

const props = defineProps<{
  account: AccountRecord;
}>();

const emit = defineEmits<{
  edit: [accountId: string];
  delete: [accountId: string];
}>();

const router = useRouter();

function statusClasses(status: AccountRecord['status']): string {
  if (status === 'Activo') {
    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
  }

  return 'bg-slate-500/10 text-slate-600 dark:text-slate-300';
}

function handleEdit(): void {
  emit('edit', props.account.id);
}

function handleDelete(): void {
  emit('delete', props.account.id);
}

function openAccountDetails(): void {
  router.push({
    name: 'admin.accounts.view',
    params: { accountId: props.account.id },
  });
}
</script>

<template>
  <AppCard
    class="relative block cursor-pointer overflow-hidden rounded-xl p-3.5! shadow-none transition hover:border-(--app-color-border-strong)"
    role="link"
    tabindex="0"
    @click="openAccountDetails"
    @keydown.enter="openAccountDetails"
    @keydown.space.prevent="openAccountDetails"
  >
    <div
      class="pointer-events-none absolute inset-y-0 left-0 w-24 opacity-90"
      :style="{
        background: `linear-gradient(90deg, color-mix(in srgb, ${props.account.color} 14%, transparent), transparent 78%)`,
      }"
    />

    <div class="relative grid grid-cols-[minmax(0,1fr)_auto_auto_1rem] items-start gap-x-3 gap-y-2">
      <div class="min-w-0">
        <p
          class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
        >
          {{ props.account.name }}
        </p>
      </div>

      <span
        class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-[0.04em] uppercase"
        :class="statusClasses(props.account.status)"
      >
        {{ props.account.status }}
      </span>

      <AppActionMenu
        class="shrink-0"
        @delete.prevent.stop="handleDelete"
        @edit.prevent.stop="handleEdit"
      />

      <div class="row-span-2 flex h-full items-center justify-end self-center">
        <ChevronRightIcon class="h-4 w-4 shrink-0 text-(--app-color-text-subtle)" />
      </div>

      <div class="col-span-3 flex min-w-0 items-center justify-between">
        <p
          class="text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
        >
          Balance
        </p>
        <p
          class="shrink-0 text-sm font-semibold tracking-tight whitespace-nowrap text-(--app-color-text) tabular-nums sm:text-base"
        >
          {{ props.account.balance }}
        </p>
      </div>
    </div>
  </AppCard>
</template>
