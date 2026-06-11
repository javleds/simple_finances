<script setup lang="ts">
import type { Component } from 'vue';

import type { AccountGoal } from '@/modules/accounts/schemas/accountGoalSchemas';
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
    goal: AccountGoal | null;
    open: boolean;
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
    title="Eliminar meta"
    variant="danger"
    @action="$event === 'confirm-delete-goal' && emit('confirm')"
    @close="emit('close')"
  >
    <div class="space-y-3">
      <AppText v-if="props.goal">
        Vas a eliminar
        <strong>{{ props.goal.name }}</strong
        >.
      </AppText>
      <AppText v-if="props.deleteError" class="text-(--app-color-danger)!">
        {{ props.deleteError }}
      </AppText>
    </div>
  </AppModal>
</template>
