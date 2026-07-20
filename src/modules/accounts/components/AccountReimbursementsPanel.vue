<script setup lang="ts">
import { computed, ref } from 'vue';
import { BanknotesIcon, CheckCircleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';

import type { Account, AccountPendingReimbursement } from '@/modules/accounts/types';
import {
  AppButton,
  AppCard,
  AppIconButton,
  AppModal,
  AppText,
  AppTitle,
} from '@/modules/shared/components';

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

type SelectedReimbursement = {
  account: Account;
  reimbursement: AccountPendingReimbursement;
};

type PendingBulkAction =
  | {
      kind: 'all';
      accounts: Account[];
    }
  | {
      kind: 'account';
      account: Account;
      reimbursements: AccountPendingReimbursement[];
    };

const selectedReimbursement = ref<SelectedReimbursement | null>(null);
const pendingRowAction = ref<SelectedReimbursement | null>(null);
const pendingBulkAction = ref<PendingBulkAction | null>(null);

const accountsWithReimbursements = computed(() =>
  props.accounts.filter((account) => account.pendingReimbursements.length > 0),
);

const accountsWithCurrentUserDebts = computed(() =>
  accountsWithReimbursements.value
    .map((account) => ({
      ...account,
      pendingReimbursements: currentUserDebts(account.pendingReimbursements),
    }))
    .filter((account) => account.pendingReimbursements.length > 0),
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

function currentUserDebts(
  reimbursements: AccountPendingReimbursement[],
): AccountPendingReimbursement[] {
  return reimbursements.filter((item) => reimbursementRole(item) === 'debtor');
}

function accountDebtTotal(account: Account): number {
  return currentUserDebts(account.pendingReimbursements).reduce(
    (sum, item) => sum + item.amount,
    0,
  );
}

function bulkActionTotal(action: PendingBulkAction | null): number {
  if (!action) {
    return 0;
  }

  if (action.kind === 'account') {
    return action.reimbursements.reduce((sum, item) => sum + item.amount, 0);
  }

  return action.accounts.reduce(
    (sum, account) =>
      sum + account.pendingReimbursements.reduce((accountSum, item) => accountSum + item.amount, 0),
    0,
  );
}

function bulkActionCount(action: PendingBulkAction | null): number {
  if (!action) {
    return 0;
  }

  if (action.kind === 'account') {
    return action.reimbursements.length;
  }

  return action.accounts.reduce((sum, account) => sum + account.pendingReimbursements.length, 0);
}

function bulkActionTitle(action: PendingBulkAction | null): string {
  if (!action) {
    return 'Confirmar pago';
  }

  if (action.kind === 'account') {
    return `Pagar pendientes de ${action.account.name}`;
  }

  return 'Pagar todos mis pendientes';
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
    return `Debes ${formatCurrency(reimbursement.amount)}`;
  }

  if (role === 'creditor') {
    return `Te deben ${formatCurrency(reimbursement.amount)}`;
  }

  return `${formatCurrency(reimbursement.amount)} pendiente`;
}

function reimbursementActionLabel(reimbursement: AccountPendingReimbursement): string | null {
  const role = reimbursementRole(reimbursement);

  if (role === 'debtor') {
    return 'Pagar reembolso';
  }

  if (role === 'creditor') {
    return 'Marcar reembolso recibido';
  }

  return null;
}

function canSettle(reimbursement: AccountPendingReimbursement): boolean {
  return reimbursementRole(reimbursement) !== 'other';
}

function detailsLabel(reimbursement: AccountPendingReimbursement): string {
  return `${reimbursement.fromUserName} debe a ${reimbursement.toUserName}`;
}

function openDetails(account: Account, reimbursement: AccountPendingReimbursement): void {
  selectedReimbursement.value = { account, reimbursement };
}

function closeDetails(): void {
  selectedReimbursement.value = null;
}

function confirmOrSettleRow(account: Account, reimbursement: AccountPendingReimbursement): void {
  if (reimbursement.items.length <= 1) {
    emit('settle', account.id, reimbursement);
    return;
  }

  pendingRowAction.value = { account, reimbursement };
}

function closeRowConfirmation(): void {
  pendingRowAction.value = null;
}

function confirmRowAction(): void {
  const action = pendingRowAction.value;

  if (!action) {
    return;
  }

  emit('settle', action.account.id, action.reimbursement);
  closeRowConfirmation();
}

function openAllConfirmation(): void {
  pendingBulkAction.value = {
    kind: 'all',
    accounts: accountsWithCurrentUserDebts.value,
  };
}

function openAccountConfirmation(account: Account): void {
  pendingBulkAction.value = {
    kind: 'account',
    account,
    reimbursements: currentUserDebts(account.pendingReimbursements),
  };
}

function closeBulkConfirmation(): void {
  pendingBulkAction.value = null;
}

function confirmBulkAction(): void {
  const action = pendingBulkAction.value;

  if (!action) {
    return;
  }

  if (action.kind === 'account') {
    emit('settleAccount', action.account.id, action.reimbursements);
  } else {
    emit('settleAll', action.accounts);
  }

  closeBulkConfirmation();
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
  <AppCard v-if="accountsWithReimbursements.length > 0" class="rounded-3xl">
    <div class="space-y-5">
      <div class="flex items-start gap-3">
        <div class="min-w-0 flex-1 space-y-1">
          <AppTitle as="h2" size="sm">{{ props.title }}</AppTitle>
          <AppText>
            {{ formatCurrency(totalAmount) }} entre {{ accountsWithReimbursements.length }}
            cuenta(s).
          </AppText>
        </div>

        <div class="flex shrink-0 items-center gap-1.5">
          <AppButton
            v-if="accountsWithCurrentUserDebts.length > 0"
            variant="secondary"
            :disabled="props.isTransferring"
            :loading="props.isTransferring"
            class="px-3!"
            @click="openAllConfirmation"
          >
            Pagar todo
          </AppButton>

          <div
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-(--app-color-surface-muted)"
          >
            <BanknotesIcon class="h-5 w-5 text-(--app-color-text-subtle)" />
          </div>
        </div>
      </div>

      <div
        class="divide-y divide-(--app-color-border) overflow-hidden rounded-2xl border border-(--app-color-border)"
      >
        <div
          v-for="account in accountsWithReimbursements"
          :key="account.id"
          class="bg-(--app-color-surface)"
        >
          <div class="px-3 py-3">
            <div class="flex items-center gap-3">
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-(--app-color-text)">
                  {{ account.name }}
                </p>
                <p class="text-xs text-(--app-color-text-subtle)">
                  {{ account.pendingReimbursements.length }} reembolso(s)
                  <template v-if="accountDebtTotal(account) > 0">
                    · Debes {{ formatCurrency(accountDebtTotal(account)) }}
                  </template>
                </p>
              </div>

              <AppIconButton
                v-if="currentUserDebts(account.pendingReimbursements).length > 0"
                :ariaLabel="`Pagar pendientes de ${account.name} por ${formatCurrency(accountDebtTotal(account))}`"
                :disabled="props.isTransferring"
                :loading="props.isTransferring"
                @click="openAccountConfirmation(account)"
              >
                <BanknotesIcon class="h-5 w-5" />
              </AppIconButton>
            </div>
          </div>

          <div class="divide-y divide-(--app-color-border)">
            <div
              v-for="item in account.pendingReimbursements"
              :key="transferKey(account.id, item)"
              class="flex items-center gap-3 px-3 py-3"
            >
              <div class="min-w-0 flex-1">
                <p class="truncate text-base font-semibold text-(--app-color-text) tabular-nums">
                  {{ reimbursementLabel(item) }}
                </p>
                <p class="truncate text-xs text-(--app-color-text-subtle)">
                  {{ detailsLabel(item) }}
                </p>
              </div>

              <div class="flex shrink-0 items-center gap-1">
                <AppIconButton
                  :ariaLabel="`Ver detalle de ${detailsLabel(item)}`"
                  @click="openDetails(account, item)"
                >
                  <InformationCircleIcon class="h-5 w-5" />
                </AppIconButton>

                <AppIconButton
                  v-if="canSettle(item)"
                  :ariaLabel="reimbursementActionLabel(item) ?? 'Liquidar reembolso'"
                  :disabled="props.isTransferring"
                  :loading="props.activeTransferKey === transferKey(account.id, item)"
                  @click="confirmOrSettleRow(account, item)"
                >
                  <BanknotesIcon v-if="reimbursementRole(item) === 'debtor'" class="h-5 w-5" />
                  <CheckCircleIcon v-else class="h-5 w-5" />
                </AppIconButton>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppCard>

  <AppModal
    :open="selectedReimbursement !== null"
    title="Detalle del reembolso"
    close-label="Cerrar"
    @close="closeDetails"
  >
    <div v-if="selectedReimbursement" class="space-y-4">
      <div class="space-y-1">
        <p class="text-sm font-semibold text-(--app-color-text)">
          {{ selectedReimbursement.account.name }}
        </p>
        <p class="text-sm text-(--app-color-text-subtle)">
          {{ detailsLabel(selectedReimbursement.reimbursement) }}
        </p>
        <p class="text-lg font-semibold text-(--app-color-text) tabular-nums">
          {{ formatCurrency(selectedReimbursement.reimbursement.amount) }}
        </p>
      </div>

      <div
        v-if="selectedReimbursement.reimbursement.items.length > 0"
        class="divide-y divide-(--app-color-border) overflow-hidden rounded-2xl bg-(--app-color-surface-muted)"
      >
        <div
          v-for="item in selectedReimbursement.reimbursement.items"
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

      <p
        v-else
        class="text-sm text-(--app-color-text-subtle)"
      >
        No hay movimientos individuales disponibles para este ajuste.
      </p>
    </div>
  </AppModal>

  <AppModal
    :open="pendingRowAction !== null"
    title="Confirmar pago"
    variant="warning"
    :actions="[
      { key: 'cancel', label: 'Cancelar', tone: 'neutral', autoClose: true },
      {
        key: 'confirm',
        label: 'Confirmar pago',
        tone: 'primary',
        loading: props.isTransferring,
      },
    ]"
    @action="
      ($event) => {
        if ($event === 'confirm') confirmRowAction();
      }
    "
    @close="closeRowConfirmation"
  >
    <div v-if="pendingRowAction" class="space-y-3">
      <p class="text-sm text-(--app-color-text)">
        Esta acción registrará el pago de
        <strong>{{ pendingRowAction.reimbursement.items.length }}</strong>
        movimiento(s) por
        <strong>{{ formatCurrency(pendingRowAction.reimbursement.amount) }}</strong
        >.
      </p>
      <p class="text-sm text-(--app-color-text-subtle)">
        {{ detailsLabel(pendingRowAction.reimbursement) }} en {{ pendingRowAction.account.name }}.
      </p>
    </div>
  </AppModal>

  <AppModal
    :open="pendingBulkAction !== null"
    :title="bulkActionTitle(pendingBulkAction)"
    variant="warning"
    :actions="[
      { key: 'cancel', label: 'Cancelar', tone: 'neutral', autoClose: true },
      {
        key: 'confirm',
        label: 'Confirmar pago',
        tone: 'primary',
        loading: props.isTransferring,
      },
    ]"
    @action="
      ($event) => {
        if ($event === 'confirm') confirmBulkAction();
      }
    "
    @close="closeBulkConfirmation"
  >
    <div class="space-y-3">
      <p class="text-sm text-(--app-color-text)">
        Esta acción registrará el pago de
        <strong>{{ bulkActionCount(pendingBulkAction) }}</strong>
        pendiente(s) del usuario activo por
        <strong>{{ formatCurrency(bulkActionTotal(pendingBulkAction)) }}</strong
        >.
      </p>
      <p class="text-sm text-(--app-color-text-subtle)">
        No se liquidarán pendientes de otros usuarios ni cobros marcados como recibidos.
      </p>
    </div>
  </AppModal>
</template>
