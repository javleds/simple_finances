<script setup lang="ts">
import { CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline';

import type { DashboardPendingAction } from '@/modules/admin/types/dashboard';
import { AppModal, AppText } from '@/modules/shared/components';

const props = withDefaults(
  defineProps<{
    completeError?: string | null;
    isCompleting: boolean;
    open: boolean;
    selectedAccountActions: DashboardPendingAction[];
    selectedAction: DashboardPendingAction | null;
  }>(),
  {
    completeError: null,
  },
);

const emit = defineEmits<{
  close: [];
  confirm: [];
}>();

const modalActions = [
  { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
  {
    key: 'confirm-complete',
    label: 'Completar movimiento',
    tone: 'primary' as const,
    icon: CheckIcon,
  },
];

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}

function accountActionsTotal(): number {
  return props.selectedAccountActions.reduce((sum, item) => sum + item.amount, 0);
}
</script>

<template>
  <AppModal
    :open="props.open"
    :actions="
      modalActions.map((action) => ({
        ...action,
        label:
          action.key === 'confirm-complete' && props.isCompleting ? 'Completando...' : action.label,
        disabled: action.key === 'confirm-complete' ? props.isCompleting : undefined,
      }))
    "
    title="Completar movimiento"
    variant="warning"
    @action="$event === 'confirm-complete' && emit('confirm')"
    @close="emit('close')"
  >
    <div class="space-y-3">
      <AppText v-if="props.completeError" class="text-(--app-color-danger)!">
        {{ props.completeError }}
      </AppText>

      <AppText v-if="props.selectedAction">
        Vas a marcar como completado el pendiente de
        <strong>{{ props.selectedAction.accountName }}</strong>
        por
        <strong>{{ formatCurrency(props.selectedAction.amount) }}</strong
        >.
      </AppText>

      <AppText v-else-if="props.selectedAccountActions.length > 0">
        Vas a marcar como completados los
        <strong>{{ props.selectedAccountActions.length }} pendientes de esta cuenta</strong>
        por un total de
        <strong>{{ formatCurrency(accountActionsTotal()) }}</strong
        >.
      </AppText>

      <AppText v-else>
        Confirma si quieres marcar este movimiento pendiente como completado.
      </AppText>
    </div>
  </AppModal>
</template>
