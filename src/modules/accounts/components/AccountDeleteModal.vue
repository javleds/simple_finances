<script setup lang="ts">
import type { AppModalAction } from '@/modules/shared/types/modal';
import type { Account } from '@/modules/accounts/types';
import { AppModal, AppText } from '@/modules/shared/components';

const props = withDefaults(
  defineProps<{
    account: Account | null;
    actions: ReadonlyArray<AppModalAction>;
    deleteError?: string | null;
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
    title="Eliminar cuenta"
    variant="danger"
    @action="$event === 'confirm-delete-account' && emit('confirm')"
    @close="emit('close')"
  >
    <div class="space-y-3">
      <AppText v-if="props.account">
        Vas a eliminar
        <strong>{{ props.account.name }}</strong
        >.
      </AppText>
      <AppText v-if="props.deleteError" class="text-(--app-color-danger)!">
        {{ props.deleteError }}
      </AppText>
      <AppText size="sm" tone="subtle">
        Esta acción seguirá el mismo flujo de confirmación antes de conectarse a persistencia real.
      </AppText>
    </div>
  </AppModal>
</template>
