<script setup lang="ts">
import { CheckIcon } from '@heroicons/vue/24/outline';

import type { DashboardPendingActionGroup } from '@/modules/admin/types/dashboard';
import { AppCard, AppText, AppTitle } from '@/modules/shared/components';

const props = defineProps<{
  groups: DashboardPendingActionGroup[];
}>();

const emit = defineEmits<{
  completeAccount: [accountId: string];
  completeAction: [actionId: string];
}>();

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}
</script>

<template>
  <AppCard class="rounded-3xl">
    <div class="space-y-4">
      <div class="space-y-1">
        <AppTitle as="h2" size="sm">Acciones pendientes</AppTitle>
        <AppText>Completa pagos pendientes sin salir del tablero principal.</AppText>
      </div>

      <div class="space-y-2">
        <div
          v-for="group in props.groups"
          :key="group.accountId"
          class="space-y-2 rounded-2xl border bg-(--app-color-surface-muted) px-3 py-3"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <div
            class="flex items-start justify-between gap-3 border-b pb-2"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <div class="min-w-0 space-y-1">
              <div class="flex items-center gap-2">
                <span
                  class="h-2.5 w-2.5 shrink-0 rounded-full"
                  :style="{
                    backgroundColor: group.accountColor ?? 'var(--app-color-text-subtle)',
                  }"
                />
                <p class="truncate text-sm font-semibold text-(--app-color-text)">
                  {{ group.accountName }}
                </p>
              </div>
              <p class="text-xs text-(--app-color-text-subtle)">
                {{ group.items.length }} pendientes · {{ formatCurrency(group.totalAmount) }}
              </p>
            </div>

            <button
              type="button"
              class="shrink-0 text-(--app-color-link) transition hover:text-(--app-color-link-hover) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
              aria-label="Completar movimientos pendientes de la cuenta"
              @click="emit('completeAccount', group.accountId)"
            >
              <CheckIcon class="h-5 w-5" />
            </button>
          </div>

          <div class="space-y-1">
            <div v-for="action in group.items" :key="action.id" class="rounded-xl px-1 py-2">
              <div class="min-w-0 space-y-2">
                <div class="flex items-start justify-between gap-3">
                  <p
                    class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
                  >
                    {{ action.concept }}
                  </p>

                  <button
                    type="button"
                    class="mt-0.5 shrink-0 text-(--app-color-link) transition hover:text-(--app-color-link-hover) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                    aria-label="Completar movimiento pendiente"
                    @click="emit('completeAction', action.id)"
                  >
                    <CheckIcon class="h-4 w-4" />
                  </button>
                </div>

                <p class="text-sm font-semibold whitespace-nowrap text-(--app-color-text)">
                  {{ formatCurrency(action.amount) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="props.groups.length === 0"
          class="rounded-xl border border-dashed px-4 py-4 text-center"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">No hay acciones pendientes por ahora.</AppText>
        </div>
      </div>
    </div>
  </AppCard>
</template>
