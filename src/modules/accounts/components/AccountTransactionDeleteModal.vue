<script setup lang="ts">
import type { Component } from 'vue';

import type { Transaction } from '@/modules/transactions/types';
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
    deleteError?: string | null;
    open: boolean;
    transaction: Transaction | null;
  }>(),
  {
    deleteError: null,
  },
);

const emit = defineEmits<{
  close: [];
  confirm: [];
}>();
</script>

<template>
  <AppModal
    :open="props.open"
    :actions="props.actions"
    title="Eliminar transacción"
    variant="danger"
    @action="$event === 'confirm-delete-transaction' && emit('confirm')"
    @close="emit('close')"
  >
    <div class="space-y-3">
      <AppText v-if="props.transaction">
        Vas a eliminar
        <strong>{{ props.transaction.concept }}</strong
        >.
      </AppText>
      <AppText v-if="props.deleteError" class="text-(--app-color-danger)!">
        {{ props.deleteError }}
      </AppText>
    </div>
  </AppModal>
</template>
