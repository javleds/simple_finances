<script setup lang="ts">
import type { Component } from 'vue';

import type { AccountPendingByUser } from '@/modules/accounts/types';
import { AppModal, AppText } from '@/modules/shared/components';

type ModalAction = {
  key: string;
  label: string;
  tone?: 'primary' | 'danger' | 'neutral';
  icon?: Component;
  type?: 'button' | 'submit' | 'reset';
  disabled?: boolean;
  form?: string;
  autoClose?: boolean;
};

const props = withDefaults(
  defineProps<{
    actions: ReadonlyArray<ModalAction>;
    completeError?: string | null;
    open: boolean;
    pendingUser: AccountPendingByUser | null;
  }>(),
  {
    completeError: null,
  },
);

const emit = defineEmits<{
  close: [];
  confirm: [];
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
  <AppModal
    :open="props.open"
    :actions="props.actions"
    title="Completar pendientes del usuario"
    variant="warning"
    @action="$event === 'confirm-complete-pending-by-user' && emit('confirm')"
    @close="emit('close')"
  >
    <div class="space-y-3">
      <AppText v-if="props.pendingUser">
        Vas a completar
        <strong>{{ props.pendingUser.transactionIds.length }} movimientos</strong>
        pendientes de
        <strong>{{ props.pendingUser.userName }}</strong>
        por
        <strong>{{ formatCurrency(props.pendingUser.amount) }}</strong
        >.
      </AppText>
      <AppText v-if="props.completeError" class="text-(--app-color-danger)!">
        {{ props.completeError }}
      </AppText>
    </div>
  </AppModal>
</template>
