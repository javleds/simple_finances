<script setup lang="ts">
import { ref } from 'vue';
import { ArrowPathIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';

import type { AccountPendingReimbursement } from '@/modules/accounts/types';
import {
  AppCard,
  AppHeroMetric,
  AppIconButton,
  AppModal,
  AppText,
} from '@/modules/shared/components';

const props = defineProps<{
  balance: number;
  currentUserId: string | null;
  isSharedAccount: boolean;
  pendingReimbursements?: AccountPendingReimbursement[];
}>();

const selectedReimbursement = ref<AccountPendingReimbursement | null>(null);

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}

function reimbursementLabel(item: AccountPendingReimbursement): string {
  if (props.currentUserId === item.fromUserId) {
    return `Debes ${formatCurrency(item.amount)}`;
  }

  if (props.currentUserId === item.toUserId) {
    return `Te deben ${formatCurrency(item.amount)}`;
  }

  return `${formatCurrency(item.amount)} pendiente`;
}

function reimbursementDetail(item: AccountPendingReimbursement): string {
  return `${item.fromUserName} debe a ${item.toUserName}`;
}

function itemDateLabel(date: string | null): string {
  if (!date) {
    return 'Sin fecha';
  }

  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${date}T00:00:00`));
}
</script>

<template>
  <AppCard class="rounded-3xl">
    <div class="space-y-5">
      <AppHeroMetric label="Balance" :value="formatCurrency(props.balance)">
        <template #adornment>
          <div
            class="flex h-12 w-12 items-center justify-center rounded-full border bg-(--app-color-surface-muted)"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <ArrowPathIcon class="h-5 w-5 text-(--app-color-text-subtle)" />
          </div>
        </template>
      </AppHeroMetric>

      <template v-if="props.isSharedAccount">
        <div class="border-t" :style="{ borderColor: 'var(--app-color-border)' }"></div>

        <div class="space-y-3">
          <AppText size="sm" tone="subtle">Reembolsos sugeridos</AppText>

          <div
            v-if="(props.pendingReimbursements ?? []).length > 0"
            class="divide-y divide-(--app-color-border) overflow-hidden rounded-2xl bg-(--app-color-surface-muted)"
          >
            <div
              v-for="item in props.pendingReimbursements"
              :key="`${item.fromUserId}-${item.toUserId}`"
              class="flex min-h-16 items-center gap-3 px-3 py-3"
            >
              <div class="min-w-0 flex-1">
                <p class="truncate text-base font-semibold text-(--app-color-text) tabular-nums">
                  {{ reimbursementLabel(item) }}
                </p>
                <p class="truncate text-xs text-(--app-color-text-subtle)">
                  {{ reimbursementDetail(item) }}
                </p>
              </div>

              <AppIconButton
                :ariaLabel="`Ver detalle de ${reimbursementDetail(item)}`"
                @click="selectedReimbursement = item"
              >
                <InformationCircleIcon class="h-5 w-5" />
              </AppIconButton>
            </div>
          </div>

          <div v-else class="rounded-2xl bg-(--app-color-surface-muted) px-3 py-3">
            <AppText size="sm" tone="subtle">No hay reembolsos sugeridos.</AppText>
          </div>
        </div>
      </template>
    </div>
  </AppCard>

  <AppModal
    :open="selectedReimbursement !== null"
    title="Detalle del reembolso"
    close-label="Cerrar"
    @close="selectedReimbursement = null"
  >
    <div v-if="selectedReimbursement" class="space-y-4">
      <div class="space-y-1">
        <p class="text-sm text-(--app-color-text-subtle)">
          {{ reimbursementDetail(selectedReimbursement) }}
        </p>
        <p class="text-lg font-semibold text-(--app-color-text) tabular-nums">
          {{ formatCurrency(selectedReimbursement.amount) }}
        </p>
      </div>

      <div
        v-if="selectedReimbursement.items.length > 0"
        class="divide-y divide-(--app-color-border) overflow-hidden rounded-2xl bg-(--app-color-surface-muted)"
      >
        <div
          v-for="item in selectedReimbursement.items"
          :key="item.transactionId"
          class="px-3 py-3"
        >
          <div class="flex items-start justify-between gap-3">
            <p class="min-w-0 text-sm font-medium text-(--app-color-text)">
              {{ item.concept }}
            </p>
            <p class="shrink-0 text-sm font-semibold text-(--app-color-text) tabular-nums">
              {{ formatCurrency(item.amount) }}
            </p>
          </div>
          <p class="mt-1 text-xs text-(--app-color-text-subtle)">
            {{ itemDateLabel(item.occurredAt) }}
          </p>
        </div>
      </div>

      <p v-else class="text-sm text-(--app-color-text-subtle)">
        No hay movimientos individuales disponibles para este ajuste.
      </p>
    </div>
  </AppModal>
</template>
