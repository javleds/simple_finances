<script setup lang="ts">
import type { Component } from 'vue';

import type { AccountMember } from '@/modules/accounts/types';
import { AppInput, AppModal, AppText } from '@/modules/shared/components';

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

const editPercentage = defineModel<string>('editPercentage', { required: true });

const props = withDefaults(
  defineProps<{
    actions: ReadonlyArray<ModalAction>;
    canSubmit: boolean;
    open: boolean;
    saveError?: string | null;
    selectedUser: AccountMember | null;
  }>(),
  {
    saveError: null,
  },
);

const emit = defineEmits<{
  close: [];
  save: [];
}>();
</script>

<template>
  <AppModal
    :open="props.open"
    :actions="props.actions"
    title="Editar porcentaje"
    variant="default"
    @action="$event === 'submit-edit-user' && emit('save')"
    @close="emit('close')"
  >
    <div class="space-y-5">
      <AppText v-if="props.selectedUser">
        Ajusta la participación de <strong>{{ props.selectedUser.name }}</strong> dentro de esta
        cuenta.
      </AppText>

      <AppInput
        id="account-user-percentage"
        v-model="editPercentage"
        label="Porcentaje"
        type="number"
        inputmode="decimal"
        min="0"
        max="100"
        step="0.01"
        placeholder="0.00"
        :error="
          props.saveError ??
          (editPercentage && !props.canSubmit
            ? 'El porcentaje debe estar entre 0 y 100.'
            : undefined)
        "
        required
      />
    </div>
  </AppModal>
</template>
