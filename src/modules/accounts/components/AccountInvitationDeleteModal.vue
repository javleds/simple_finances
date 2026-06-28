<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';
import { AppModal, AppText } from '@/modules/shared/components';

const props = withDefaults(
  defineProps<{
    actions: ReadonlyArray<AppModalAction>;
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
