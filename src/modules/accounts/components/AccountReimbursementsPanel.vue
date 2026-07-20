<script setup lang="ts">
import { computed } from 'vue';
import { BanknotesIcon } from '@heroicons/vue/24/outline';

import type { Account, AccountPendingReimbursement } from '@/modules/accounts/types';
import { AppButton, AppCard, AppText, AppTitle } from '@/modules/shared/components';

const props = withDefaults(
  defineProps<{
    accounts: Account[];
    activeTransferKey?: string | null;
    currentUserId?: string | null;
    isTransferring?: boolean;
    title?: string;
  }>(),
  {
    activeTransferKey: null,
    currentUserId: null,
    isTransferring: false,
    title: 'Reembolsos pendientes',
  },
);

const emit = defineEmits<{
  settle: [accountId: string, reimbursement: AccountPendingReimbursement];
  settleAccount: [accountId: string, reimbursements: AccountPendingReimbursement[]];
  settleAll: [accounts: Account[]];
}>();

const accountsWithReimbursements = computed(() =>
  props.accounts.filter((account) => account.pendingReimbursements.length > 0),
);

const totalAmount = computed(() =>
  accountsWithReimbursements.value.reduce(
    (sum, account) =>
      sum + account.pendingReimbursements.reduce((accountSum, item) => accountSum + item.amount, 0),
    0,
  ),
);

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}

function transferKey(accountId: string, reimbursement: AccountPendingReimbursement): string {
  return `${accountId}:${reimbursement.fromUserId}:${reimbursement.toUserId}:${reimbursement.amount}`;
}

function reimbursementRole(
  reimbursement: AccountPendingReimbursement,
): 'debtor' | 'creditor' | 'other' {
  if (props.currentUserId === reimbursement.fromUserId) {
    return 'debtor';
  }

  if (props.currentUserId === reimbursement.toUserId) {
    return 'creditor';
  }

  return 'other';
}

function reimbursementLabel(reimbursement: AccountPendingReimbursement): string {
  const role = reimbursementRole(reimbursement);

  if (role === 'debtor') {
    return `Debes a ${reimbursement.toUserName}`;
  }

  if (role === 'creditor') {
    return `${reimbursement.fromUserName} te debe`;
  }

  return `${reimbursement.fromUserName} debe a ${reimbursement.toUserName}`;
}

function reimbursementActionLabel(reimbursement: AccountPendingReimbursement): string {
  const role = reimbursementRole(reimbursement);

  if (role === 'debtor') {
    return 'Pagar';
  }

  if (role === 'creditor') {
    return 'Marcar recibido';
  }

  return 'Liquidar';
}

function bulkActionLabel(reimbursements: AccountPendingReimbursement[]): string {
  if (reimbursements.every((item) => reimbursementRole(item) === 'debtor')) {
    return 'Pagar todos';
  }

  if (reimbursements.every((item) => reimbursementRole(item) === 'creditor')) {
    return 'Marcar recibidos';
  }

  return 'Liquidar todos';
}
</script>

<template>
  <AppCard v-if="accountsWithReimbursements.length > 0" class="rounded-3xl">
    <div class="space-y-4">
      <div class="flex items-start justify-between gap-3">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">{{ props.title }}</AppTitle>
          <AppText
            >{{ formatCurrency(totalAmount) }} por liquidar entre cuentas compartidas.</AppText
          >
        </div>

        <div class="flex shrink-0 items-center gap-2">
          <AppButton
            v-if="accountsWithReimbursements.length > 1"
            variant="secondary"
            :disabled="props.isTransferring"
            :loading="props.isTransferring"
            @click="emit('settleAll', accountsWithReimbursements)"
          >
            {{
              bulkActionLabel(
                accountsWithReimbursements.flatMap((account) => account.pendingReimbursements),
              )
            }}
          </AppButton>

          <div
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-(--app-color-surface-muted)"
          >
            <BanknotesIcon class="h-5 w-5 text-(--app-color-text-subtle)" />
          </div>
        </div>
      </div>

      <div class="space-y-3">
        <div
          v-for="account in accountsWithReimbursements"
          :key="account.id"
          class="space-y-3 rounded-2xl bg-(--app-color-surface-muted) p-3"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-(--app-color-text)">
                {{ account.name }}
              </p>
              <p class="text-xs text-(--app-color-text-subtle)">
                {{ account.pendingReimbursements.length }} reembolso(s)
              </p>
            </div>

            <AppButton
              variant="outline"
              :disabled="props.isTransferring"
              :loading="props.isTransferring"
              @click="emit('settleAccount', account.id, account.pendingReimbursements)"
            >
              {{ bulkActionLabel(account.pendingReimbursements) }}
            </AppButton>
          </div>

          <div class="space-y-2">
            <div
              v-for="item in account.pendingReimbursements"
              :key="transferKey(account.id, item)"
              class="flex items-center justify-between gap-3 rounded-xl bg-(--app-color-surface) px-3 py-2"
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-(--app-color-text)">
                  {{ reimbursementLabel(item) }}
                </p>
                <p class="text-xs text-(--app-color-text-subtle)">
                  {{ formatCurrency(item.amount) }}
                </p>
              </div>

              <AppButton
                variant="secondary"
                :disabled="props.isTransferring"
                :loading="props.activeTransferKey === transferKey(account.id, item)"
                @click="emit('settle', account.id, item)"
              >
                {{ reimbursementActionLabel(item) }}
              </AppButton>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppCard>
</template>
