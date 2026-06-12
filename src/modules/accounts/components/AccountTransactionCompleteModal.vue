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
    open: boolean;
    saveError?: string | null;
    transaction: Transaction | null;
  }>(),
  {
    saveError: null,
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
    title="Completar transacción"
    variant="success"
    @action="$event === 'confirm-complete-transaction' && emit('confirm')"
    @close="emit('close')"
  >
    <div class="space-y-3">
      <AppText v-if="props.transaction">
        Vas a marcar
        <strong>{{ props.transaction.concept }}</strong>
        como completada.
      </AppText>
      <AppText v-if="props.saveError" class="text-(--app-color-danger)!">
        {{ props.saveError }}
      </AppText>
    </div>
  </AppModal>
</template>
