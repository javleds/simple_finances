<script setup lang="ts">
import type { Component } from 'vue';

import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';
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
    invitation: AccountInvite | null;
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
    title="Eliminar invitación"
    variant="danger"
    @action="$event === 'confirm-delete-invitation' && emit('confirm')"
    @close="emit('close')"
  >
    <div class="space-y-3">
      <AppText v-if="props.invitation">
        Vas a eliminar la invitación de
        <strong>{{ props.invitation.email }}</strong
        >.
      </AppText>
      <AppText v-if="props.deleteError" class="text-(--app-color-danger)!">
        {{ props.deleteError }}
      </AppText>
    </div>
  </AppModal>
</template>
