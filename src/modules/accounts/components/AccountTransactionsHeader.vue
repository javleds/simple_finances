<script setup lang="ts">
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

import type { AccountPendingReimbursement } from '@/modules/accounts/types';
import { AppAvatarValueRow, AppCard, AppHeroMetric, AppText } from '@/modules/shared/components';

const props = defineProps<{
  balance: number;
  currentUserId: string | null;
  isSharedAccount: boolean;
  pendingReimbursements?: AccountPendingReimbursement[];
}>();

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

          <div v-if="(props.pendingReimbursements ?? []).length > 0" class="space-y-2">
            <AppAvatarValueRow
              v-for="item in props.pendingReimbursements"
              :key="`${item.fromUserId}-${item.toUserId}`"
              :name="reimbursementLabel(item)"
              :value="reimbursementDetail(item)"
            />
          </div>

          <div v-else class="rounded-2xl bg-(--app-color-surface-muted) px-3 py-3">
            <AppText size="sm" tone="subtle">No hay reembolsos sugeridos.</AppText>
          </div>
        </div>
      </template>
    </div>
  </AppCard>
</template>
